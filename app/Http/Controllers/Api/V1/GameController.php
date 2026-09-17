<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Game\GameRegistry;
use App\Domain\Subscription\Services\FeatureGate;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Library بازی‌های مجاز — فصل ۵-۵ و ۸-۲ (فیلتر بر اساس Plan) */
final class GameController extends Controller
{
    use ApiResponse;

    /** GET /api/v1/games — بازی‌های فعال با نشان دسترسی Plan فعلی */
    public function index(Request $request): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');
        $gate = FeatureGate::for($store);

        $games = Game::query()
            ->where('is_active', true)
            ->with('category:id,code,name')
            ->orderBy('sort')
            ->get()
            ->map(fn (Game $game) => [
                'code' => $game->code,
                'name' => $game->name,
                'category' => $game->category?->code,
                'description' => $game->description,
                'is_implemented' => GameRegistry::has($game->code),
                'plan_allowed' => $gate->canAccessGame($game),
            ]);

        return $this->ok([
            'games' => $games,
            'categories' => config('games.categories'),
        ]);
    }

    /** GET /api/v1/games/{code}/config-schema — Schema پویا برای Wizard (فصل ۵-۳) */
    public function configSchema(string $code): JsonResponse
    {
        $plugin = GameRegistry::for($code);

        return $this->ok([
            'game' => $plugin::metadata()->toArray(),
            'schema' => $plugin::configSchema(),
        ]);
    }
}
