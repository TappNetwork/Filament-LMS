# Signed URLs for Private Storage

This package supports signed URLs for private storage systems like AWS S3, allowing secure access to media files stored in private buckets.

## Configuration

To enable signed URLs, update your `config/filament-lms.php` file:

```php
'media' => [
    // Enable signed URLs for private storage
    'use_signed_urls' => true,
    // Expiration time in minutes (default: 60)
    'signed_url_expiration' => 60,
],
```

## Usage

### For Course Images

Course images will automatically use signed URLs when the configuration is enabled:

```php
// This will return a signed URL if use_signed_urls is true
$course = Course::find(1);
$imageUrl = $course->image_url; // Automatically signed if configured
```

### For Other Media

Use the `HasMediaUrl` trait in your models:

```php
use Tapp\FilamentLms\Traits\HasMediaUrl;

class YourModel extends Model implements HasMedia
{
    use HasMediaUrl;

    public function getImageUrlAttribute()
    {
        return $this->getMediaUrl('your-collection');
    }
}
```

### Manual Usage

You can also use the trait methods directly:

```php
// Get signed URL for a specific collection
$url = $model->getMediaUrl('images');

// Get signed URL for a specific conversion
$url = $model->getMediaUrl('images', 'thumb');
```

## Storage Configuration

### AWS S3 with Private Bucket

Ensure your S3 configuration is set up for private access:

```php
// config/filesystems.php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => false,
    'visibility' => 'private', // Important: Set to private
],
```

### Local Storage

Even with local storage, signed URLs can be useful for temporary access:

```php
// config/filesystems.php
'local' => [
    'driver' => 'local',
    'root' => storage_path('app'),
    'permissions' => [
        'file' => [
            'public' => 0644,
            'private' => 0600,
        ],
        'dir' => [
            'public' => 0755,
            'private' => 0700,
        ],
    ],
    'visibility' => 'private', // Set to private for signed URLs
],
```

## Security Considerations

1. **Expiration Time**: Set appropriate expiration times for your use case
2. **HTTPS Only**: Always use HTTPS in production
3. **Access Control**: Consider implementing additional access controls in your application logic

## Troubleshooting

### Images Not Loading

1. Check that `use_signed_urls` is set to `true` in your config
2. Verify your storage configuration is correct
3. Ensure your S3 bucket is configured for private access
4. Check that your AWS credentials have the necessary permissions

### Performance Considerations

- Signed URLs are generated on each request
- Consider caching strategies for frequently accessed images
- Monitor your S3 request costs

## Overriding the Default Behavior

You can override the `getImageUrlAttribute()` method in your Course model to implement custom logic:

```php
public function getImageUrlAttribute()
{
    // Your custom logic here
    if ($this->isPremiumContent()) {
        return $this->getMediaUrl('courses');
    }

    return 'https://picsum.photos/200';
}
```
