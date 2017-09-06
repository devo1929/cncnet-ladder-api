<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Http\Services\LadderService;
use \App\Http\Services\GameService;
use \App\Http\Services\PlayerService;
use \App\Http\Services\PointService;
use \App\Http\Services\AuthService;

class ApiQuickMatchController extends Controller
{
    private $ladderService;
    private $gameService;
    private $playerService;
    private $pointService;
    private $authService;

    public function __construct()
    {
        $this->ladderService = new LadderService();
        $this->gameService = new GameService();
        $this->playerService = new PlayerService();
        $this->authService = new AuthService();
    }

    public function mapListRequest(Request $request, $ladderAbbrev = null)
    {
        //$qmMaps = \App\QmMap::where('ladder_id', $this->ladderService->getLadderByGame($ladderAbbrev)->id)->get();
        return \App\QmMap::findMapsByLadder($this->ladderService->getLadderByGame($ladderAbbrev)->id);
    }

    public function sidesListRequest(Request $request, $ladderAbbrev = null)
    {
        $ladder = $this->ladderService->getLadderByGame($ladderAbbrev);
        $rules = $ladder->qmLadderRules()->first();
        $allowed_sides = $rules->allowed_sides();
        $sides = $ladder->sides()->get();

        return $sides->filter(function ($side) use(&$allowed_sides)
                              {
                                  return in_array($side->local_id, $allowed_sides);
                              } );
    }

    public function matchRequest(Request $request, $ladderAbbrev = null, $playerName = null)
    {
        $ladder = $this->ladderService->getLadderByGame($ladderAbbrev);
        $ladder_rules = $ladder->qmLadderRules()->first();
        $player = $this->playerService->findPlayerByUsername($playerName, $ladder);

        if ($player == null)
        {
            return array("type"=>"fail", "description" => "$playerName is not registered in $ladderAbbrev");
        }
        $rating = $player->rating()->first()->rating;

        $qmPlayer = \App\QmMatchPlayer::where('player_id', $player->id)
                                      ->where('waiting', true)->first();


        switch ($request->type ) {
        case "quit":
            if ($qmPlayer != null)
            {
                $qmPlayer->delete();
            }
            return array("type" => "quit");
            break;
        case "update":
            if ($qmPlayer != null)
            {
            }
            break;

        case "match me up":
            /* This matchup system is restful, a player will have to check in to see if there
             * is a matchup waitin.
             * If there is already a matchup then all these top level ifs will fall through
             * and the game info will be sent.
             * Else we'll try to set up a match.
             */
            if ($qmPlayer == null)
            {
                $qmPlayer = new \App\QmMatchPlayer();
                $qmPlayer->player_id = $player->id;
                $qmPlayer->ladder_id = $player->ladder_id;
                $qmPlayer->map_bitfield = $request->map_bitfield;
                $qmPlayer->waiting = true;

                // color, chosen_side, actual_side and saving is done in the next if-statement
                $qmPlayer->qm_match_id = null;
                $qmPlayer->tunnel_id = null;

                $qmPlayer->ip_address = $request->ip_address;
                $qmPlayer->port = $request->ip_port;

                $qmPlayer->lan_ip = $request->lan_ip;
                $qmPlayer->lan_port = $request->lan_port;

                $qmPlayer->ipv6_address = $request->ipv6_address;
                $qmPlayer->ipv6_port = $request->ipv6_port;
            }

            if ($qmPlayer->qm_match_id == null)
            {
                // Update the player info
                $qmPlayer->chosen_side = $request->side;

                $all_sides = $ladder_rules->all_sides();
                $sides = $ladder_rules->allowed_sides();

                if ($request->side == -1)
                {
                    $qmPlayer->actual_side = $sides[rand(0, count($all_sides) - 1)];
                }
                else if (in_array($request->side, $sides))
                {
                    $qmPlayer->actual_side = $request->side;
                }
                else {
                    return array("type" => "error", "description" => "Side ({$request->side}) is not allowed");
                }

                $qmPlayer->map_bitfield = $request->map_bitfield;

                try
                {
                    // This will error out when a not null field is null
                    $qmPlayer->save();
                }
                catch (Exception $e)
                {
                    return array("type" => "error", "description" => "null or missing field");
                }

                /* Try to find a matchup
                 * Matchups are based on the player's rating,
                 * The absolute value of the difference of me and every other player is calculated.
                 * Any players whose difference is greater 100 is thrown out with some exceptions
                 * If a player has been waiting a long time for a matchup he should get some special
                 * treatment.  To allow for this, the player rating difference gets wait time, in
                 * seconds, subtracted from it.
                 * If 2 players rated 1200, and 1400 are the only players a match won't be made
                 * until one player has been waiting for 100 seconds 1400-1200-100seconds = 100
                 *
                 * The ratio of seconds to points should be tunable TODO.
                 */
                $qmOpns = \App\QmMatchPlayer::where('qm_match_players.id', '<>', $qmPlayer->id)
                        ->where('waiting', true)
                        ->whereNull('qm_match_id')
                        ->join('player_ratings', 'player_ratings.player_id', '=', 'qm_match_players.player_id')
                        ->selectRAW( "qm_match_players.id as id, waiting, qm_match_players.player_id as player_id,"
                                    ."ladder_id, map_bitfield, chosen_side, actual_side, ip_address, port, lan_ip"
                                    .",lan_port, ipv6_address, ipv6_port, color, location, qm_match_id, tunnel_id,"
                                    ."qm_match_players.created_at as created_at"
                                    .", qm_match_players.updated_at as updated_at"
                                    .", ABS($rating - rating)"
                                    ." - TIMESTAMPDIFF(SECOND, now(), qm_match_players.created_at) as importance")
                        ->having('importance', '<', $ladder_rules->max_difference)
                        ->orderBy('importance', 'asc')
                        ->get();

                $qmOpns = $qmOpns->shuffle();

                if ($qmOpns->count() >= $ladder_rules->player_count - 1)
                {
                    // Randomly choose the opponents from the best matches. To prevent
                    // long runs of identical matchups.
                    $qmOpns = $qmOpns->shuffle()->take($ladder_rules->player_count - 1);

                    // Randomly select a map
                    $common_maps = array();
                    $common_bits = $qmPlayer->map_bitfield;

                    foreach ($qmOpns as $opn)
                    {
                        $common_bits &= $opn->map_bitfield;
                    }

                    $map_count = \App\QmMap::where('ladder_id', $qmPlayer->ladder_id)->get()->count();
                    for ($i = 0; $i < $map_count; $i++)
                    {
                        $bit = 1 << $i;
                        if ($bit & $common_bits)
                        {
                            $common_maps[] = $i;
                        }
                    }
                    $map_idx = rand(0, count($common_maps) - 1);


                    // Create the qm_matches db entry
                    $qmMatch = new \App\QmMatch();
                    $qmMatch->status = "Started";
                    $qmMatch->ladder_id = $qmPlayer->ladder_id;
                    $qmMatch->qm_map_id = \App\QmMap::where('bit_idx', $common_maps[$map_idx])
                                                    ->where('ladder_id', $qmMatch->ladder_id)->first()->id;
                    $qmMatch->seed = rand(-2147483647, 2147483647);
                    $qmMatch->save();

                    $qmMap = $qmMatch->map()->first();
                    $spawn_order = explode(',', $qmMap->spawn_order);

                    // Set up player specific information
                    // Color will be used for spawn location
                    $qmPlayer->color = 0;
                    $qmPlayer->location = $spawn_order[$qmPlayer->color] - 1;
                    $qmPlayer->qm_match_id = $qmMatch->id;
                    $qmPlayer->tunnel_id = $qmMatch->seed + $qmPlayer->color;
                    $qmPlayer->save();

                    $color = 1;
                    foreach ($qmOpns as $opn)
                    {
                        $opn->color = $color++;
                        $opn->location = $spawn_order[$opn->color] - 1;
                        $opn->qm_match_id = $qmMatch->id;
                        $opn->tunnel_id = $qmMatch->seed + $opn->color;
                        $opn->save();
                    }
                }
                else {
                    // We couldn't make a match
                    return array("type" => "please wait", "checkback" => 10, "no_sooner_than" => 5);
                }
            }
            // If we've made it this far, lets send the spawn details

            $spawnStruct = array("type" => "spawn");
            $qmPlayer->waiting = false;
            $qmMatch = \App\QmMatch::find($qmPlayer->qm_match_id);
            $qmMap = $qmMatch->map()->first();
            $map = $qmMap->map()->first();

            $spawnStruct["spawn"]["SpawnLocations"] = array();


            $spawnStruct["spawn"]["Settings"] = array_filter(
                [  "UIGameMode" =>     $qmMap->game_mode
                  ,"UIMapName" =>      $map->name
                  ,"MapHash" =>        $map->hash
                  ,"GameSpeed" =>      $qmMap->speed
                  ,"Seed" =>           $qmMatch->seed
                  ,"GameID" =>         $qmMatch->seed
                  ,"WOLGameID" =>         $qmMatch->seed
                  ,"Credits" =>        $qmMap->credits
                  ,"UnitCount" =>      $qmMap->units
                  ,"TechLevel" =>      $qmMap->tech
                  ,"Host" =>           "No"
                  ,"IsSpectator" =>    "No"
                  ,"AIPlayers" =>      0
                  ,"Name" =>           $qmPlayer->player()->first()->username
                  ,"Port" =>           $qmPlayer->port
                  ,"Side" =>           $qmPlayer->actual_side
                  ,"Color" =>          $qmPlayer->color
                  ,"Firestorm" =>      b_to_ini($qmMap->firestorm)
                  ,"ShortGame" =>      b_to_ini($qmMap->short_game)
                  ,"MultiEngineer" =>  b_to_ini($qmMap->multi_eng)
                  ,"MCVRedeploy" =>    b_to_ini($qmMap->redeploy)
                  ,"Crates" =>         b_to_ini($qmMap->crates)
                  ,"Bases" =>          b_to_ini($qmMap->bases)
                  ,"HarvesterTruce" => b_to_ini($qmMap->harv_truce)
                  ,"AlliesAllowed" =>  b_to_ini($qmMap->allies)
                  ,"BridgeDestroy" =>  b_to_ini($qmMap->bridges)
                  ,"FogOfWar" =>       b_to_ini($qmMap->fog)
                  ,"BuildOffAlly" =>   b_to_ini($qmMap->build_ally)
                  ,"MultipleFactory"=> b_to_ini($qmMap->mutli_factory)
                  ,"AimableSams" =>    b_to_ini($qmMap->aimable_sams)
                  ,"AttackNeutralUnits" => b_to_ini($qmMap->attack_neutral)
                  ,"Superweapons" =>   b_to_ini($qmMap->supers)

                   // Filter null values
            ], function($var){return !is_null($var);} );


            // Write the Others sections

            $allPlayers = $qmMatch->players()->where('id', '<>', $qmPlayer->id)->orderBy('color', 'ASC')->get();
            $other_idx = 1;

            $multi_idx = $qmPlayer->color + 1;
            $spawnStruct["spawn"]["SpawnLocations"]["Multi{$multi_idx}"] = $qmPlayer->location;

            foreach ($allPlayers as $opn)
            {
                $spawnStruct["spawn"]["Other{$other_idx}"] = [
                    "Name" => $opn->player()->first()->username,
                    "Side" => $opn->actual_side,
                    "Color" => $opn->color,
                    "Ip" => $opn->ip_address,
                    "Port" => $opn->port,
                    "IPv6" => $opn->ipv6_address,
                    "PortV6" => $opn->ipv6_port,
                    "LanIP" => $opn->lan_ip,
                    "LanPort" => $opn->lan_port
                ];
                $multi_idx = $opn->color + 1;
                $spawnStruct["spawn"]["SpawnLocations"]["Multi{$multi_idx}"] = $opn->location;
                $other_idx++;
            }
            $qmPlayer->waiting = false;
            $qmPlayer->save();

            return $spawnStruct;
            break;
        default:
            return array("type" => "error", "description" => "unknown type: {$request->type}");
            break;
        }
    }
}

function b_to_ini($bool)
{
    if ($bool == null) return $bool;
    return $bool ? "Yes" : "No";
}
