@extends('layouts.app')
@section('title', 'Ladder')

@section('cover')
/images/feature/feature-index.jpg
@endsection

@section('feature')
<div class="feature-background sub-feature-background">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-8 col-md-offset-2">
                <h1>
                    CnCNet <strong>Ladders</strong>
                </h1>
                <p>
                   Play, Compete, <strong>Conquer!</strong>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('content')
<section class="light-texture game-detail supported-games">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h3>CnCNet <strong>Quick Match</strong> </h3>
            </div>
        </div>
        <div class="feature">
            <div class="row">
                @foreach($ladders as $history)
                <div class="col-xs-12 col-sm-6 col-md-4" style="margin-bottom:20px">
                    <a href="/ladder/{{ $history->short . "/" . $history->ladder->abbreviation }}/" title="{{ $history->ladder->name }}" class="ladder-link">
                        <div class="ladder-cover cover-{{ $history->ladder->abbreviation}}" style="background-image: url('/images/ladder/{{ $history->ladder->abbreviation . "-cover.png" }}')">
                            <div class="details">
                                <div class="type">
                                    <h1>{{ $history->ladder->name }}</h1>
                                    <p class="lead">1<strong>vs</strong>1</p>
                                </div>
                            </div>
                            <div class="badge-cover">
                                <ul class="list-inline">
                                    <li>
                                        <p>{{ Carbon\Carbon::parse($history->starts)->format('F Y') }} Competition</p>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
{{--
<section class="dark-texture game-detail supported-games">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h3>Recently played games</h3>
            </div>
        </div>
        <div class="feature">
        </div>
    </div>
</section>
--}}
@endsection