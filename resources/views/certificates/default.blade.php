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
      <img src="{{ asset('/images/certificate-logo.png') }}" class="mx-auto" />
      <h1 class="text-5xl font-extrabold my-4 font-serif italic">
          {{ $user->name }}
      </h1>
      <div class="text-2xl">
          <h2>
              {{ __('The Institute for Public Health Institute certifies that') }}
          </h2>
          <h1 class="font-extrabold my-4">
              {{ $user->name }}
          </h1>
          <h2>
              {{ __('has successfully completed') }}
          </h2>
          <h1 class="text-2xl font-extrabold my-4">
              {{$course->name}}
          </h1>
          <h2>
              {{$course->description}}
          </h2>
      </div>

      <div class="columns-2 mt-14">
          <div class="p-4">
            <!-- padding is to even out mismatched signature sizes -->
      <img src="{{ asset('/images/president-signature.png') }}" class="pt-4 mx-auto" />
              <hr />
              <h4>
                  {{ __('Michael Rhein, ') }}
                  <span class="italic">
                      {{__('President and CEO')}}
                  </span>
              </h4>
          </div>
          <div class="p-4">
            <img src="{{ asset('/images/coordinator-signature.png') }}" class="pb-4 mx-auto" />
              <hr />
              <h4>
                  {{ __('Dwyan Monroe, ') }}
                <span class="italic">
                  {{__('Program Coordinator')}}
                </span>
              </h4>
          </div>
      </div>
    </div>
  </body>
</html>
