# Evidence Directory

This directory stores screenshots and artifacts captured during verification runs.

## Expected Files

After a successful verification run:

- `dashboard.png` - LMS dashboard showing course library
- `step-viewer.png` - Course step viewer with sidebar and video
- `course-completed.png` - Course completion/certificate page
- `certificate.pdf` - Generated certificate PDF (if certificate-builder configured)

## Naming Convention

For multiple verification runs or feature-specific tests:
- `{feature}-{timestamp}.png` - Feature-specific screenshots
- `{test-name}-{pass|fail}.png` - Test result captures

Keep evidence from successful verification runs to serve as regression baselines.
