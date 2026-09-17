<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameCategory;
use Illuminate\Database\Seeder;

/**
 * Game Registry اولیه — ده بازی MVP (فصل ۱-۴ و ۵-۵ سند معماری).
 * افزودن بازی جدید فقط: رکورد جدول games + پلاگین + ثبت در config/games.php
 */
final class GameSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'chance', 'name' => 'شانس'],
            ['code' => 'pick', 'name' => 'انتخاب'],
            ['code' => 'scratch', 'name' => 'Scratch'],
            ['code' => 'skill', 'name' => 'مهارتی'],
            ['code' => 'arcade', 'name' => 'Arcade'],
            ['code' => 'quiz', 'name' => 'Quiz'],
            ['code' => 'lottery', 'name' => 'Lottery'],
        ];

        foreach ($categories as $i => $category) {
            GameCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name'], 'sort' => $i],
            );
        }

        $games = [
            ['code' => 'wheel', 'name' => 'چرخ شانس (Lucky Wheel)', 'category' => 'chance', 'sort' => 1],
            ['code' => 'dice', 'name' => 'تاس شانس (Lucky Dice)', 'category' => 'chance', 'sort' => 2],
            ['code' => 'scratch', 'name' => 'کارت خراشیدنی (Scratch Card)', 'category' => 'scratch', 'sort' => 3],
            ['code' => 'pick-box', 'name' => 'انتخاب جعبه (Pick a Box)', 'category' => 'pick', 'sort' => 4],
            ['code' => 'pick-card', 'name' => 'انتخاب کارت (Pick a Card)', 'category' => 'pick', 'sort' => 5],
            ['code' => 'lucky-ticket', 'name' => 'بلیط شانس (Lucky Ticket)', 'category' => 'lottery', 'sort' => 6],
            ['code' => 'quiz', 'name' => 'کوئیز (Quiz)', 'category' => 'quiz', 'sort' => 7],
            ['code' => 'memory', 'name' => 'بازی حافظه (Memory)', 'category' => 'skill', 'sort' => 8],
            ['code' => 'reaction', 'name' => 'بازی سرعت (Reaction)', 'category' => 'skill', 'sort' => 9],
            ['code' => 'claw', 'name' => 'دستگیره شانس (Lucky Claw)', 'category' => 'arcade', 'sort' => 10],
        ];

        foreach ($games as $game) {
            Game::query()->updateOrCreate(
                ['code' => $game['code']],
                [
                    'name' => $game['name'],
                    'game_category_id' => GameCategory::query()->where('code', $game['category'])->value('id'),
                    'is_active' => true,
                    'sort' => $game['sort'],
                ],
            );
        }
    }
}
