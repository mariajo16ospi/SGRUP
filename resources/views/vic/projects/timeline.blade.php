@extends('layouts.app')
@section('title', 'Trazabilidad · ' . $project->name)
@section('content')
    @include('vic.projects._header')

    <section class="card">
        <h2>Trazabilidad completa del proyecto</h2>
        <p class="hint" style="margin-bottom:16px">{{ $events->count() }} registros · de lo más reciente a lo más antiguo</p>
        <ol class="timeline">
            @foreach ($events as $e)
                <li>
                    <b>{{ $e['text'] }}</b>
                    <span class="meta">{{ $e['who'] }} · {{ $e['role'] }} · {{ date_es($e['at']) }} {{ $e['at']->format('H:i') }}</span>
                </li>
            @endforeach
        </ol>
    </section>
@endsection
