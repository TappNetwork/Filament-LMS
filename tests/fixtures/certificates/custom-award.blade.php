<div class="pb-1.5 pr-1.5 pl-1.5 mx-auto bg-linear-to-r from-lime-400 via-sky-500 to-cyan-300">
<div style="background-image: url({{ asset('/img/header-green.jpg') }});">
    <h1>{{ $course->name }}</h1>
    <h2>{{ __('CERTIFICATE OF COMPLETION') }}</h2>
    <p>{{ __('AWARDED TO:') }}</p>
    <h1>{{ $user->name }}</h1>
    <p>{{ $dateEarned }}</p>
    <p>
        This certificate recognizes your commitment to promoting inclusive and accessible reproductive healthcare as a valued participant in the Delaware Contraceptive Access Now training.
    </p>
    <p>
        <img src="{{ asset('/img/DE_DHSS-logo-red-wide.png') }}" />
    </p>
</div>
</div>
