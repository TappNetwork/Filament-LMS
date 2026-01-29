<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'LMS') }}</title>

  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
    
    body {
      font-family: 'Poppins', sans-serif;
    }
    
    .certificate {
      width: 1050px;
      height: 774px;
      margin: 0 auto;
      position: relative;
      background: white;
      padding: 3rem;
      box-sizing: border-box;
    }
    
    .certificate-content {
      width: 100%;
      height: 100%;
      border: 2px solid #1a1a1a;
      position: relative;
      padding: 0;
      display: flex;
      flex-direction: column;
      box-sizing: border-box;
    }
    
    .certificate-inner {
      margin: 0.75rem;
      height: calc(100% - 1.5rem);
      display: flex;
      flex-direction: column;
      box-sizing: border-box;
    }
    
    .logo-section {
      height: 120px;
      padding: 1.5rem 3rem 2.5rem;
      text-align: center;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      box-sizing: border-box;
    }
    
    .logo-section img {
      max-height: 90px;
      max-width: 300px;
      width: auto;
      height: auto;
      margin: 0 auto;
      display: block;
      object-fit: contain;
    }
    
    .main-content {
      flex: 1;
      padding: 1.5rem 4rem;
      text-align: center;
      display: flex;
      flex-direction: column;
      justify-content: center;
      box-sizing: border-box;
    }
    
    .certificate-title {
      font-size: 4.5rem;
      font-weight: 700;
      letter-spacing: 0.15em;
      color: #000;
      margin-bottom: 0.5rem;
      line-height: 1;
    }
    
    .certificate-subtitle {
      font-size: 1.5rem;
      letter-spacing: 0.25em;
      font-weight: 400;
      color: #000;
      margin-bottom: 2.5rem;
    }
    
    .certificate-subtitle.no-footer {
      margin-bottom: 4.5rem;
    }
    
    .recipient-label {
      font-size: 1rem;
      letter-spacing: 0.05em;
      color: #000;
      margin-bottom: 1.25rem;
      text-transform: uppercase;
      font-weight: 400;
    }
    
    .recipient-name {
      font-size: 3.5rem;
      font-weight: 700;
      color: #000;
      margin-bottom: 1.5rem;
      line-height: 1.2;
      text-transform: uppercase;
    }
    
    .completion-text {
      font-size: 1.125rem;
      line-height: 1.8;
      color: #000;
      max-width: 700px;
      margin: 0 auto 1.5rem;
      font-weight: 400;
    }
    
    .course-name {
      font-weight: 700;
      color: #000;
      text-transform: uppercase;
    }
    
    .completion-date {
      font-weight: 700;
    }
    
    .footer-section {
      height: 100px;
      padding: 0 3rem 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      flex-shrink: 0;
      box-sizing: border-box;
    }
    
    .signature-line {
      flex: 1;
      max-width: 250px;
      text-align: center;
    }
    
    .signature-line-border {
      border-bottom: 2px solid #1a1a1a;
      margin-bottom: 0.5rem;
      height: 30px;
    }
    
    .signature-label {
      font-size: 0.75rem;
      color: #6a6a6a;
      text-transform: uppercase;
      letter-spacing: 0.1em;
    }
    
    .certificate-id {
      font-size: 0.75rem;
      color: #9a9a9a;
      letter-spacing: 0.05em;
    }
  </style>
  </head>

  <body class="bg-gray-100">
    <div class="certificate">
      <div class="certificate-content">
        <div class="certificate-inner">
          <!-- Logo Section -->
          <div class="logo-section">
            <img src="{{ asset(config('filament-lms.certificate_logo') ?: config('filament-lms.brand_logo', 'images/logo.png')) }}" alt="Logo">
          </div>

          <!-- Main Content -->
          <div class="main-content">
            <h1 class="certificate-title">CERTIFICATE</h1>
            <h2 class="certificate-subtitle {{ (!config('filament-lms.certificate_show_signatures') && !config('filament-lms.certificate_show_id')) ? 'no-footer' : '' }}">OF COMPLETION</h2>

            <p class="recipient-label">This certificate is awarded to</p>

            <h3 class="recipient-name">{{ $user->name }}</h3>

            <p class="completion-text">
              successfully completed <span class="course-name">{{ $course->name }}</span> on <span class="completion-date">{{ $dateEarned }}</span>.
            </p>
          </div>

          <!-- Footer Section -->
          <div class="footer-section">
            @if(config('filament-lms.certificate_show_signatures'))
              <div class="signature-line">
                <div class="signature-line-border"></div>
                <p class="signature-label">Authorized Signature</p>
              </div>
            @else
              <div class="signature-line" style="visibility: hidden;"></div>
            @endif
            
            @if(config('filament-lms.certificate_show_id'))
              <div class="certificate-id">
                ID {{ strtoupper(substr(md5($course->id . '-' . $user->id . '-' . ($dateEarned ?? '')), 0, 12)) }}
              </div>
            @else
              <div class="certificate-id" style="visibility: hidden;"></div>
            @endif
            
            @if(config('filament-lms.certificate_show_signatures'))
              <div class="signature-line">
                <div class="signature-line-border"></div>
                <p class="signature-label">{{ $user?->name ?? 'Authorized By' }}</p>
              </div>
            @else
              <div class="signature-line" style="visibility: hidden;"></div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
