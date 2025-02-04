@extends('layouts.app')
@section('title', 'Ladder')

@section('cover')
/images/feature/feature-{{ $ladder->abbreviation }}.jpg
@endsection

@section('feature')
<div class="game">
<div class="feature-background sub-feature-background">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-8 col-md-offset-2">
                <h1>
                    {{ $ladder->name }}
                </h1>
                <p>
                    CnCNet Ladders <strong>1vs1</strong>
                </p>
                <p>
                </p>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@section('content')
<?php $card = \App\Card::find($player->card_id); ?>

<div class="player">
    <div class="feature-background player-card {{ $card->short or "no-card" }}">
        <div class="container">

            <div class="player-header">
                <div class="player-stats">

                    <h1 class="username">
                        {{ $player->username }}
                    </h1>
                       {{ $user->getBan() }}
                </div>
            </div>
        </div>
    </div>
    <div class="feature-footer-background">
        <div class="container">
            <div class="player-footer">
                <div class="player-dials">
                </div>
                <div class="player-achievements">
                    @if ($player->game_count >= 200)
                    <div>
                        <img src="/images/badges/achievement-games.png" style="height:50px"/>
                        <h5 style="font-weight: bold; text-transform:uppercase; font-size: 10px;">Played <br/>200+ Games</h5>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>


<div class="player">
    <section class="dark-texture">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    @include("components.form-messages")

                    <h3>{{$banDesc}}</h3>
                    <form method="POST" action="/admin/moderate/{{$ladder->id}}/player/{{$player->id}}/editban/{{$id}}">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="ban_type" value="{{$ban_type}}" >
                        <input type="hidden" name="admin_id" value="{{$admin_id}}" >
                        <input type="hidden" name="user_id" value="{{$user_id}}" >
                        <input type="hidden" name="expires" value="{{$expires}}" >
                        <input type="hidden" name="start_or_end" value="{{$start_or_end}}" >
                        <input type="hidden" name="ip_address_id" value="{{$ip_address_id}}" >

                        <div class="form-group">
                            <label for="internal_note" >Note for Internal Use</label>
                            <textarea class="form-control" id="internal_note" name="internal_note">{{$internal_note}}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="plubic_reason" >Publicly Viewable Reason</label>
                            <textarea class="form-control" id="plubic_reason" name="plubic_reason">{{$plubic_reason}}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg" >Save</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection
