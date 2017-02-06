<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class LadderType extends Model 
{
	protected $table = 'ladder_types';

	protected $fillable = ['name', 'type', 'match'];
}
