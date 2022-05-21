<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QmMatch extends Model
{

    //
    public function players()
    {
        return $this->hasMany('App\Models\QmMatchPlayer');
    }

    public function map()
    {
        return $this->belongsTo('App\QmMap', 'qm_map_id');
    }

    public function ladder()
    {
        return $this->belongsTo('App\Models\Ladder');
    }

    public function states()
    {
        return $this->hasMany('App\Models\QmMatchState');
    }

    public function qmConnectionStats()
    {
        return $this->hasMany('\App\Models\QmConnectionStats');
    }
}
