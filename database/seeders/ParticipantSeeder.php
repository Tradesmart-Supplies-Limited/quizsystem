<?php

namespace Database\Seeders;

use App\Models\Participant;
use Illuminate\Database\Seeder;

class ParticipantSeeder extends Seeder
{
    public const PEOPLE = [
        ['Grace Wanjiru', 'Grace', 'Operations Manager'],
        ['Brian Otieno', 'Brian', 'Warehouse Supervisor'],
        ['Faith Achieng', 'Faith', 'Inventory Controller'],
        ['Kevin Mwangi', 'Kevin', 'Logistics Coordinator'],
        ['Mercy Njeri', 'Mercy', 'Procurement Officer'],
        ['Daniel Kiprotich', 'Daniel', 'Forklift Operator'],
        ['Sharon Atieno', 'Sharon', 'Customer Service Lead'],
        ['Peter Kamau', 'Peter', 'Dispatch Clerk'],
        ['Ann Muthoni', 'Ann', 'Stores Clerk'],
        ['Samuel Odhiambo', 'Samuel', 'Driver'],
        ['Lucy Wambui', 'Lucy', 'Health & Safety Officer'],
        ['James Ochieng', 'James', 'Fleet Officer'],
        ['Esther Chebet', 'Esther', 'Quality Controller'],
        ['Michael Njoroge', 'Michael', 'Picker / Packer'],
        ['Joyce Akinyi', 'Joyce', 'Receiving Clerk'],
        ['Victor Mutua', 'Victor', 'Driver'],
        ['Caroline Nyambura', 'Caroline', 'Operations Assistant'],
        ['Collins Barasa', 'Collins', 'Stock Controller'],
    ];

    public function run(): void
    {
        foreach (self::PEOPLE as [$full, $display, $job]) {
            Participant::updateOrCreate(['full_name' => $full], [
                'display_name' => $display, 'department' => 'Operations', 'job_title' => $job, 'is_active' => true,
            ]);
        }
    }
}
