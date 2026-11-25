<?php

namespace Database\Seeders;

use App\Models\Participant;
use Illuminate\Database\Seeder;

class ParticipantSeeder extends Seeder
{
    public const PEOPLE = [
        ['Bright Twange Mumbi', 'Bright', 'Operations Officer'],
        ['Chilekwa Chinungi', 'Chilekwa', 'Operations Officer'],
        ['Chofwe Chola', 'Chofwe', 'Operations Officer'],
        ['Collins Kalima', 'Collins', 'Operations Officer'],
        ['Emmanuel Kaumba Kaumba', 'Emmanuel', 'Operations Officer'],
        ['Emmanuel Gwaza Ndhlovu', 'Gwaza', 'Operations Officer'],
        ['Enggie Mubanga Chama', 'Enggie', 'Team Leader'],
        ['Godfrey Miti', 'Godfrey', 'Operations Officer'],
        ['Jenovic Chinyanta', 'Jenovic', 'Operations Officer'],
        ['Kaluba Banda', 'Kaluba', 'Operations Officer'],
        ['Kiluwe Mutima Bigalel', 'Kiluwe', 'Client Relations & Operations Coordinator'],
        ['Mapesho Shipilo', 'Mapesho', 'Operations Officer'],
        ['Martha Nachalwe', 'Martha', 'Operations Officer'],
        ['Maurille Khonde', 'Maurille', 'Operations Officer'],
        ['Michelle Ngonga', 'Michelle', 'Assistant Supervisor'],
        ['Musondah Nachula', 'Musondah', 'Operations Officer'],
        ['Pamela Mwale', 'Pamela', 'Operations Officer'],
        ['Samantha Chanda Musosa', 'Samantha', 'Operations Officer'],
        ['Tiza Livele', 'Tiza', 'Operations Officer'],
        ['Tusembe Kangwa', 'Tusembe', 'Acting-Accounts Clerk'],
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
