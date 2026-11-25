<?php

namespace Database\Seeders;

use App\Models\ReactionMessage;
use Illuminate\Database\Seeder;

class ReactionSeeder extends Seeder
{
    public function run(): void
    {
        $messages = [
            'correct' => ['CORRECT!', 'OPERATIONS GENIUS!', 'EXCELLENT!', "THAT'S THE ANSWER!", 'BRAIN POWER!', 'CHAMPION MOVE!', 'Ulenwa Drink Nshi?', 'Wamano Uyu Last!!'],
            'incorrect' => ['OOPS!', 'NOT QUITE!', 'THE WAREHOUSE IS WAITING!', 'TRY AGAIN NEXT TIME!', 'THE ANSWER ESCAPED!', 'THAT WAS A CLOSE ONE!', 'THE BOSS IS WATCHING!', 'ARE YOU SURE?'],
        ];
        foreach ($messages as $type => $list) {
            foreach ($list as $m) {
                ReactionMessage::firstOrCreate(['type' => $type, 'message' => $m], ['is_active' => true]);
            }
        }
    }
}
