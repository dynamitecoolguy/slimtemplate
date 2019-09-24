<?php

namespace Hoge\Model;

class PlayerCreatedLog
{
    private $player_id;
    private $created_at;

    public function toArray()
    {
        return [
            'player_id' => $this->player_id,
            'created_at' => $this->created_at
        ];
    }
}
