@extends('layouts.app')
@section('title',$title)
@section('page-title',$title)
@section('page-subtitle',$subtitle)
@section('content')
<div class="panel"><h2>{{ $title }}</h2><p>{{ $subtitle }}</p><ul class="help-list">@foreach($notes as $note)<li>{{ $note }}</li>@endforeach</ul></div>
@endsection
