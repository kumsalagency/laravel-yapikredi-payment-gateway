@extends('payment::redirect')

@section('body')
    <form method="post" action="<?php echo $formData['gateway']; ?>" id="threeDForm">
        <?php foreach ($formData['inputs'] as $key => $value): ?>
        <input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>">
        <?php endforeach; ?>
        <button class="theme-btn btn-style-one btn-reverse" type="submit"><span>@lang('front/basket.buttons.redirect')</span></button>
    </form>
    <script>document.getElementById("threeDForm").submit();</script>
@endsection