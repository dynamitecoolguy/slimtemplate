<?php

namespace Hoge\Model;

class Player
{
    private $player_id;
    private $player_name;

    public function toArray()
    {
        return [
            'player_id' => $this->player_id,
            'player_name' => $this->player_name
        ];
    }
}
