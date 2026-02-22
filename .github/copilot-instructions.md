# Laravel Google Photos API - Copilot & Contributor Guide

This guide provides comprehensive instructions for GitHub Copilot and contributors on how to set up, use, and contribute to the Laravel Google Photos API package.

## Quick Start

This package provides a Laravel integration for the Google Photos Library API, enabling you to upload photos, manage albums, and use the Google Photos Picker API in your Laravel applications.

### Requirements

- PHP >= 8.3
- Laravel >= 12.0
- Google Photos Library API credentials
- Laravel Socialite (for OAuth authentication)

## Installation & Setup

### 1. Install Package

```bash
composer require revolution/laravel-google-photos
```

### 2. Get Google API Credentials

1. Visit [Google Cloud Console](https://developers.google.com/console)
2. Enable **Photos Library API** and **Google Photos Picker API**
   - ⚠️ Be careful not to select "Google Picker API" (different from Photos Picker API)
3. Create OAuth 2.0 client credentials
4. Set up authorized redirect URIs for your application

### 3. Configure Laravel

#### Add to `config/services.php`:
```php
'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
    'redirect'      => env('GOOGLE_REDIRECT', ''),
],
```

#### Add to `.env`:
```
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret
GOOGLE_REDIRECT=https://yourapp.com/auth/google/callback
```

#### Create `config/google.php`:
```php
<?php

return [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'scopes' => [
        'https://www.googleapis.com/auth/photoslibrary',
        'https://www.googleapis.com/auth/photoslibrary.sharing',
    ],
    'access_type' => 'offline',
    'prompt' => 'consent select_account',
];
```

## Main Usage Patterns

### 1. Basic Photo Upload (Two-Step Process)

```php
use Revolution\Google\Photos\Facades\Photos;

// Step 1: Upload file and get token
$uploadToken = Photos::withToken($user->refresh_token)
    ->upload($fileContent, $fileName);

// Step 2: Create media item from token
$result = Photos::batchCreate([$uploadToken]);
```

### 2. User Model Integration

Add the `PhotosLibrary` trait to your User model:

```php
use Revolution\Google\Photos\Traits\PhotosLibrary;

class User extends Authenticatable
{
    use PhotosLibrary;

    protected function tokenForPhotoLibrary(): array|string
    {
        return $this->refresh_token;
        
        // Or return array for custom credentials:
        // return [
        //     'client_id' => $this->client_id,
        //     'client_secret' => $this->client_secret,
        //     'refresh_token' => $this->refresh_token,
        // ];
    }
}

// Usage with trait
$albums = $user->photos()->listAlbums();
```

### 3. Album Management

```php
use Google\Photos\Library\V1\PhotosLibraryResourceFactory;

// Create album
$newAlbum = Photos::withToken($token)
    ->createAlbum(PhotosLibraryResourceFactory::album('My Album'));

// List albums
$albums = Photos::withToken($token)->listAlbums();

// Get specific album
$album = Photos::withToken($token)->getAlbum($albumId);
```

### 4. Google Photos Picker API

```php
use Revolution\Google\Photos\Facades\Picker;
use Revolution\Google\Photos\Support\Token;

// Get access token
$accessToken = Token::toAccessToken($refreshToken);

// Create picker session
$picker = Picker::withToken($accessToken)->create();
// Redirect user to: $picker['pickerUri']

// Check if user finished selecting
$session = Picker::withToken($accessToken)->get($picker['id']);
if ($session['mediaItemsSet']) {
    // Get selected media items
    $mediaItems = Picker::withToken($accessToken)->list($picker['id']);
}
```

### 5. OAuth Setup with Socialite

```php
// Redirect to Google
public function redirect()
{
    return Socialite::driver('google')
        ->scopes(config('google.scopes'))
        ->with([
            'access_type' => config('google.access_type'),
            'prompt' => config('google.prompt'),
        ])
        ->redirect();
}

// Handle callback
public function callback()
{
    $user = Socialite::driver('google')->user();
    
    $loginUser = User::updateOrCreate(
        ['email' => $user->email],
        [
            'name' => $user->name,
            'refresh_token' => $user->refreshToken,
        ]
    );
    
    auth()->login($loginUser);
    return redirect('/home');
}
```

## Development Guidelines

### Project Structure

```
src/
├── Contracts/        # Interfaces
├── Facades/         # Laravel Facades (Photos, Picker)
├── PhotosClient.php # Main API client
├── PickerClient.php # Picker API client
├── Providers/       # Service providers
├── Support/         # Helper classes (Token)
└── Traits/          # PhotosLibrary trait

tests/Feature/       # Feature tests
docs/               # Documentation
├── upload.md       # Upload guide
├── picker.md       # Picker API guide
├── oauth.md        # OAuth setup
├── trait.md        # Trait usage
└── macro.md        # Extending functionality
```

### Running Tests

```bash
# Install dependencies
composer install

# Run all tests
vendor/bin/phpunit

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

Current test coverage: 21 tests covering all major functionality.

### Code Style

The project uses Laravel Pint for code styling:

```bash
# Check code style
vendor/bin/pint --test

# Fix code style issues
vendor/bin/pint
```

### Extending Functionality (Macros)

You can extend the Photos facade with custom methods:

```php
// In AppServiceProvider::boot()
use Revolution\Google\Photos\Facades\Photos;

Photos::macro('customMethod', function ($param) {
    return $this->getService()->someApiMethod($param);
});

// Usage
$result = Photos::customMethod($value);
```

### Testing Helpers

For testing, use the Token fake helper:

```php
use Revolution\Google\Photos\Support\Token;

// In tests
Token::fake(token: 'test-token');
$token = Token::toAccessToken('refresh_token'); // Returns 'test-token'

// Reset
Token::fake(token: null);
```

## Important Limitations & Best Practices

### Copilot Environment Restrictions

⚠️ **CRITICAL**: The Copilot firewall causes errors when connecting to `www.googleapis.com`

- Google API calls will fail in the Copilot development environment
- This affects testing and development of Google API functionality
- GitHub Actions and production environments have no such restrictions
- **Workaround**: Use mock data or skip API calls when testing in Copilot environment
- Real API functionality should be tested in GitHub Actions or local development environments

**Note**: All examples and tests should be designed to handle API connectivity failures gracefully when running in restricted environments.

### Google Photos API Limitations

- **You can only access files uploaded via your API** - not existing user photos
- For production use with other users' accounts, Google review is required
- Best practice: Use only for uploading to your own account during development
- Rate limits apply - see [Google's documentation](https://developers.google.com/photos/library/guides/api-limits-quotas)

### Security Considerations

- Store refresh tokens securely in your database
- Never commit API credentials to version control
- Use environment variables for all sensitive configuration
- Implement proper error handling for API failures

### Error Handling

```php
try {
    $result = Photos::withToken($token)->upload($content, $filename);
} catch (\Exception $e) {
    // Handle API errors appropriately
    Log::error('Google Photos upload failed: ' . $e->getMessage());
}
```

## Contributing

### Before Contributing

1. Read the [UPGRADING.md](../UPGRADING.md) for breaking changes
2. Ensure PHP >= 8.2 and Laravel >= 11.0 compatibility
3. Follow existing code patterns and style
4. Add tests for new functionality
5. Update documentation as needed

### Pull Request Guidelines

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass: `vendor/bin/phpunit`
5. Check code style: `vendor/bin/pint --test`
6. Submit pull request with clear description

### CI/CD Pipeline

The project uses GitHub Actions:
- **tests.yml**: Runs tests on PHP 8.2, 8.3, 8.4
- **lint.yml**: Checks code style with Pint
- Code coverage reporting with Qlty

## Reference Documentation

### Internal Documentation
- [Upload Guide](../docs/upload.md) - Detailed upload process
- [Picker API Guide](../docs/picker.md) - Picker API implementation
- [OAuth Setup](../docs/oauth.md) - Authentication setup
- [Trait Usage](../docs/trait.md) - User model integration
- [Macro Guide](../docs/macro.md) - Extending functionality
- [Upgrade Guide](../UPGRADING.md) - Migration from v5 to v6

### External Resources
- [Google Photos Library API](https://developers.google.com/photos/library/guides/get-started-library)
- [Google Photos Picker API](https://developers.google.com/photos/picker/guides/get-started-picker)
- [Laravel Socialite Documentation](https://laravel.com/docs/socialite)
- [API Limits & Quotas](https://developers.google.com/photos/library/guides/api-limits-quotas)

### Package Dependencies
- [google/photos-library](https://github.com/google/php-photoslibrary) - Core Google Photos API client
- [revolution/laravel-google-sheets](https://github.com/revolution/laravel-google-sheets) - Shared Google API utilities

## Troubleshooting

### Common Issues

1. **"Access Token Invalid"**: Refresh token may have expired - re-authenticate user
2. **"API Not Enabled"**: Ensure Photos Library API is enabled in Google Cloud Console
3. **"Insufficient Permissions"**: Check OAuth scopes configuration
4. **"Upload Failed"**: Verify file size limits and supported formats

### Getting Help

- Check existing [Issues](https://github.com/invokable/laravel-google-photos/issues)
- Review [Discussions](https://github.com/invokable/laravel-google-photos/discussions)
- Consult Google Photos API documentation for API-specific issues

---

*This guide is maintained for the Laravel Google Photos API package. For the most up-to-date information, always refer to the official documentation and Google's API documentation.*
