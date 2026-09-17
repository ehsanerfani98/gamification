<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Campaign\Actions\PublishCampaignAction;
use App\Domain\Game\GameRegistry;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignRule;
use App\Models\Game;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** CRUD کمپین — فصل ۸-۲ (مسیرهای Merchant) */
final class CampaignController extends Controller
{
    use ApiResponse;

    /** GET /api/v1/campaigns */
    public function index(Request $request): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $campaigns = $store->campaigns()
            ->with('game:id,code,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Campaign $c) => $this->present($c));

        return $this->ok(['campaigns' => $campaigns]);
    }

    /** POST /api/v1/campaigns — ساخت کمپین به‌صورت Draft (فصل ۱-۳) */
    public function store(Request $request): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'game_code' => ['required', 'string', 'exists:games,code'],
            'config' => ['required', 'array'],
            'rules' => ['sometimes', 'array'],
            'rules.*.type' => ['required_with:rules', Rule::in([
                CampaignRule::TYPE_MAX_TOTAL_PLAYS,
                CampaignRule::TYPE_NEW_CUSTOMER_ONLY,
                CampaignRule::TYPE_MAX_DAILY_WINS,
            ])],
            'rules.*.value' => ['nullable'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'theme' => ['nullable', 'array'],
        ]);

        $game = Game::query()->where('code', $data['game_code'])->firstOrFail();

        // اعتبارسنجی اختصاصی پلاگین — لایه دفاع اول (فصل ۵-۳)
        $plugin = GameRegistry::for($game->code);
        $validator = $plugin->validateConfig($data['config']);

        if ($validator->fails()) {
            return $this->fail('INVALID_GAME_CONFIG', 'پیکربندی بازی معتبر نیست.', 422, $validator->errors()->all());
        }

        $campaign = $store->campaigns()->create([
            'game_id' => $game->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'status' => Campaign::STATUS_DRAFT,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'theme' => $data['theme'] ?? null,
        ]);

        $campaign->configuration()->create([
            'schema_version' => '1',
            'config' => $data['config'],
        ]);

        foreach ($data['rules'] ?? [] as $rule) {
            $campaign->rules()->create([
                'type' => $rule['type'],
                'value' => $rule['value'] ?? null,
            ]);
        }

        return $this->created(['campaign' => $this->present($campaign->load('configuration', 'rules', 'game'))]);
    }

    /** GET /api/v1/campaigns/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $campaign = $store->campaigns()->with('configuration', 'rules', 'game')->find($id);

        // Cross-Tenant → 404 (Global Scope + جست‌وجو در قلمرو Store)
        if ($campaign === null) {
            abort(404);
        }

        return $this->ok(['campaign' => $this->present($campaign)]);
    }

    /** PATCH /api/v1/campaigns/{id} — ویرایش فقط در Draft */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $campaign = $store->campaigns()->with('configuration')->find($id);

        if ($campaign === null) {
            abort(404);
        }

        if ($campaign->status !== Campaign::STATUS_DRAFT) {
            return $this->fail('INVALID_CAMPAIGN_STATE', 'کمپین منتشرشده قابل ویرایش نیست.', 409);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'config' => ['sometimes', 'array'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'theme' => ['nullable', 'array'],
        ]);

        if (isset($data['config'])) {
            $plugin = GameRegistry::for($campaign->game->code);
            $validator = $plugin->validateConfig($data['config']);

            if ($validator->fails()) {
                return $this->fail('INVALID_GAME_CONFIG', 'پیکربندی بازی معتبر نیست.', 422, $validator->errors()->all());
            }

            $campaign->configuration->update([
                'config' => $data['config'],
            ]);
        }

        $campaign->fill(collect($data)->except('config')->all())->save();

        return $this->ok(['campaign' => $this->present($campaign->load('configuration', 'rules', 'game'))]);
    }

    /** POST /api/v1/campaigns/{id}/publish — انتشار و دریافت لینک عمومی */
    public function publish(Request $request, int $id): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $campaign = $store->campaigns()->find($id);

        if ($campaign === null) {
            abort(404);
        }

        $campaign = app(PublishCampaignAction::class)->handle($campaign);

        return $this->ok([
            'campaign' => $this->present($campaign->load('configuration', 'rules', 'game')),
            'public_url' => url('/c/'.$campaign->slug),
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'campaign';

        do {
            $slug = Str::limit($base, 50).'-'.Str::lower(Str::random(5));
        } while (Campaign::query()->where('slug', $slug)->exists());

        return $slug;
    }

    private function present(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'slug' => $campaign->slug,
            'status' => $campaign->status,
            'game' => $campaign->game?->only(['code', 'name']),
            'config' => $campaign->configuration?->config,
            'rules' => $campaign->rules->map(fn ($r) => ['type' => $r->type, 'value' => $r->value]),
            'theme' => $campaign->theme,
            'starts_at' => $campaign->starts_at?->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
            'public_url' => url('/c/'.$campaign->slug),
        ];
    }
}
