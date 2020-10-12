@extends('payment::redirect')

@section('body')
    <form method="post" action="{{ $formData['gateway'] ?? '' }}" id="threeDForm">
        @foreach($formData['inputs'] ?? [] as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <button class="theme-btn btn-style-one btn-reverse" type="submit"><span>@lang('payment::buttons.redirect')</span></button>
    </form>
    <script>document.getElementById("threeDForm").submit();</script>
@endsection