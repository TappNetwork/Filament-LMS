<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'DHSS LMS API') }}</title>

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    .certificate {
      height: 774px;
      width: 1050px;
      margin-top: 10px;
      padding: 10px;
    }
    .certificate-border {
      height: 100%;
      width: 100%;
      padding:20px;
    }
  </style>
  </head>

  <body>
  <div class="m-auto certificate font-serif antialiased text-center border-zinc-400 border-8">
    <div class="certificate-border border-zinc-300 border-4">
      <h1 class="text-7xl font-extrabold my-16 font-serif italic">
          {{ $user->name }}
      </h1>
      <div class="text-2xl">
          <h2 class="mb-16">
              {{ __('has successfully completed') }}
          </h2>
          <h1 class="text-5xl font-extrabold mb-16">
              {{$course->name}}
          </h1>
          <h2>
              {{$course->description}}
          </h2>
      </div>

    </div>
  </body>
</html>
