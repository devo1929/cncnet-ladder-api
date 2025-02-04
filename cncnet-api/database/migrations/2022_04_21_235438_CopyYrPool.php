<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CopyYrPool extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		#Copy over existing YR ladder Map Pool into RA2 ladder Map pool
		$ra2LadderId = \App\Ladder::where('abbreviation', 'ra2')->first()->id;
		$newPool = new \App\MapPool;
		$newPool->name='Red Alert 2 Map Pool';
		$newPool->ladder_id = $ra2LadderId;
		$newPool->save();

		$yrLadder = \App\Ladder::where('abbreviation', 'yr')->first();
		#copy over maps
		$yrMaps = \App\Map::where('ladder_id', $yrLadder->id)->get();
		foreach($yrMaps as $yrMap) {
			$newMap = $yrMap->replicate();
			$newMap->ladder_id = $ra2LadderId;
			$newMap->save();
		}

		$yrQmMaps = \App\QmMap::where('map_pool_id', $yrLadder->map_pool_id)->get();
		#copy qm maps
		foreach($yrQmMaps as $yrQmMap) {
			$newQmMap = $yrQmMap->replicate();
			$newQmMap->ladder_id = $ra2LadderId;
			$newQmMap->map_pool_id = $newPool->id;

			$map_id = \App\Map::where('ladder_id', $ra2LadderId)
				->where('hash', $yrQmMap->map->hash)
				->first()->id;
			$newQmMap->map_id = $map_id;
			$newQmMap->save();
		}
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		//
	}

}
