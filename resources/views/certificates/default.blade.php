<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'LMS') }}</title>

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    .certificate {
      height: 774px;
      width: 1050px;
      margin: 0 auto;
      position: relative;
      background: white;
      padding: 4rem;
    }
    .certificate-content {
      height: 100%;
      width: 100%;
      border: 4px solid #2B3242;
      position: relative;
      padding: 3rem;
    }
    .certificate-title {
      font-size: 3.5rem;
      line-height: 3.5rem;
      font-weight: 800;
      letter-spacing: 0.1em;
    }
    .certificate-subtitle {
      font-size: 1.75rem;
      letter-spacing: 0.15em;
      margin-bottom: 3rem;
    }
    .recipient-name {
      font-size: 3.5rem;
      font-weight: 300;
      margin-bottom: 1rem;
    }
    .completed-text {
      font-size: 1.35rem;
      letter-spacing: 0.05em;
      line-height: 1.5;
    }
    .certificate-logo {
      margin-top: 4rem;
      margin-bottom: 1rem;
    }
  </style>
  </head>

  <body class="bg-gray-100">
    <div class="certificate">
      <div class="certificate-content">
        <!-- Certificate content -->
        <div class="text-center">
          <h1 class="certificate-title">CERTIFICATE</h1>
          <h2 class="certificate-subtitle">OF COMPLETION</h2>

          <p class="mb-8 text-xl">THIS CERTIFICATE IS AWARDED TO</p>

          <h2 class="font-serif uppercase recipient-name">{{ $user->name }}</h2>

          <p class="completed-text">successfully completed <span class="font-bold uppercase">{{ $course->name }}</span> on <span class="font-bold">{{ now()->format('F d, Y') }}</span>.</p>

          <!-- Logo -->
          <div class="certificate-logo">
            <img src="{{ asset(config('filament-lms.brand_logo', 'images/logo.png')) }}" alt="COE Logo" class="max-w-md mx-auto">
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
