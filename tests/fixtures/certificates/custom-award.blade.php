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
