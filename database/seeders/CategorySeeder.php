<?php

namespace Database\Seeders;

use App\Models\QuestionCategory;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public const CATEGORIES = [
        ['Operations', '#4EAADF', 'gear-wide-connected'],
        // ['Warehouse', '#86C440', 'box-seam'],
        // ['Procurement', '#F5A623', 'cart-check'],
        // ['Logistics', '#5C7AEA', 'truck'],
        // ['Customer Service', '#E94B6A', 'headset'],
        // ['Health and Safety', '#FF7F50', 'cone-striped'],
        // ['Company Policies', '#9B6BDF', 'journal-check'],
        // ['General Knowledge', '#2EC4B6', 'globe2'],
        // ['Workshop Topics', '#FFD23F', 'easel'],
        // ['Compliance', '#A3A8B0', 'shield-check'],
        // ['Team Challenges', '#00B894', 'people-fill'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $i => [$name, $color, $icon]) {
            QuestionCategory::updateOrCreate(['name' => $name], [
                'color' => $color, 'icon' => $icon, 'is_active' => true, 'sort_order' => $i,
            ]);
        }
    }
}
