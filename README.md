# Google Photos API for Laravel

[![Maintainability](https://qlty.sh/badges/70d6e01e-6c2d-40ba-985b-769820516ea7/maintainability.svg)](https://qlty.sh/gh/invokable/projects/laravel-google-photos)
[![Code Coverage](https://qlty.sh/badges/70d6e01e-6c2d-40ba-985b-769820516ea7/test_coverage.svg)](https://qlty.sh/gh/invokable/projects/laravel-google-photos)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/invokable/laravel-google-photos)

A Laravel package providing seamless integration with the Google Photos Library API and Google Photos Picker API. Upload photos, manage albums, and interact with Google Photos directly from your Laravel applications.

## Overview

This package enables Laravel applications to:

- **Upload photos** to Google Photos with automatic media item creation
- **Manage albums** - create, list, and update album information  
- **List media items** and search through uploaded content
- **Use Google Photos Picker** to let users select photos from their Google Photos library
- **OAuth 2.0 authentication** with proper token management and refresh handling

**Important**: Due to Google Photos API limitations, you can only access and manage content that was uploaded via your application. For accessing existing user photos, use the Google Photos Picker API.

## Quick Start

### 1. Install the Package

```bash
composer require revolution/laravel-google-photos

php artisan vendor:publish --tag="google-config"
```

### 2. Get Google API Credentials

1. Visit the [Google Cloud Console](https://developers.google.com/console)
2. Enable **Photos Library API** and **Google Photos Picker API**  
   ⚠️ Be careful not to select "Google Picker API" (different from Photos Picker API)
3. Create OAuth 2.0 client credentials
4. Set authorized redirect URIs for your application

### 3. Configure Laravel

Add to your `config/services.php`:

```php
'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
    'redirect'      => env('GOOGLE_REDIRECT', ''),
],
```

Edit `config/google.php`:

```php
<?php

return [
    'client_id'        => env('GOOGLE_CLIENT_ID', ''),
    'client_secret'    => env('GOOGLE_CLIENT_SECRET', ''),
    'redirect_uri'     => env('GOOGLE_REDIRECT', ''),
    'scopes'           => [
        'https://www.googleapis.com/auth/photoslibrary.appendonly',
        'https://www.googleapis.com/auth/photoslibrary.readonly.appcreateddata',
        'https://www.googleapis.com/auth/photoslibrary.edit.appcreateddata',
        'https://www.googleapis.com/auth/photospicker.mediaitems.readonly',
    ],
    'access_type'      => 'offline',
    'prompt'           => 'consent select_account',
];
```

Add to your `.env`:

```env
GOOGLE_CLIENT_ID=your_client_id_here
GOOGLE_CLIENT_SECRET=your_client_secret_here
GOOGLE_REDIRECT=https://yourapp.com/auth/google/callback
```

### 4. Quick Example

```php
use Revolution\Google\Photos\Facades\Photos;

// Upload a photo (two-step process)
$uploadToken = Photos::withToken($user->refresh_token)
    ->upload($fileContent, $fileName);

$result = Photos::batchCreate([$uploadToken]);
```

## Requirements

- PHP >= 8.3
- Laravel >= 12.0

## Installation

```bash
composer require revolution/laravel-google-photos

php artisan vendor:publish --tag="google-config"
```

## Authentication

### OAuth 2.0 Only

**This package ONLY supports OAuth 2.0 authentication.** 

❌ **Service Account authentication is NOT supported**  
❌ **API Key authentication is NOT supported**

**Why OAuth 2.0 Only?**
The Google Photos API is designed for user-centric applications and requires user consent to access personal photo libraries. Google Photos API does not support Service Account or API Key authentication methods because:

1. **Privacy by Design**: Photos are personal data requiring explicit user consent
2. **Google API Limitation**: The Photos Library API only accepts OAuth 2.0 tokens
3. **User Context Required**: All operations need to be performed on behalf of a specific user

### OAuth Setup Guide

#### Step 1: Google Cloud Console Setup

1. Go to [Google Cloud Console](https://developers.google.com/console)
2. Create a new project or select existing one
3. Enable these APIs:
   - **Photos Library API** (for uploading and managing photos)
   - **Google Photos Picker API** (for photo selection interface)
4. Go to "Credentials" → "Create Credentials" → "OAuth 2.0 Client ID"
5. Choose "Web application"
6. Add your authorized redirect URIs (e.g., `https://yourapp.com/auth/google/callback`)

#### Step 2: Laravel Configuration

The `'access_type' => 'offline'` setting is required to obtain refresh tokens for long-term access.

#### Step 3: OAuth Implementation with Laravel Socialite

```php
// routes/web.php
Route::get('/auth/google', [AuthController::class, 'redirect']);
Route::get('/auth/google/callback', [AuthController::class, 'callback']);

// AuthController.php
use Laravel\Socialite\Facades\Socialite;

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

public function callback()
{
    $user = Socialite::driver('google')->user();
    
    $loginUser = User::updateOrCreate(
        ['email' => $user->email],
        [
            'name' => $user->name,
            'refresh_token' => $user->refreshToken, // Store this!
        ]
    );
    
    auth()->login($loginUser);
    return redirect('/home');
}
```

## Usage Examples

### Upload Photos

Photos must be uploaded using a two-step process:

```php
use Revolution\Google\Photos\Facades\Photos;
use Illuminate\Http\Request;

public function uploadPhoto(Request $request)
{
    $file = $request->file('photo');
    
    // Step 1: Upload file content and get upload token
    $uploadToken = Photos::withToken($request->user()->refresh_token)
        ->upload($file->getContent(), $file->getClientOriginalName());
    
    // Step 2: Create media item from upload token
    $result = Photos::batchCreate([$uploadToken]);
    
    return response()->json($result);
}
```

### List Media Items

```php
use Revolution\Google\Photos\Facades\Photos;

public function listPhotos()
{
    $mediaItems = Photos::withToken(auth()->user()->refresh_token)
        ->listMediaItems();
    
    foreach ($mediaItems as $item) {
        echo $item->getBaseUrl() . "\n";
        echo $item->getFilename() . "\n";
    }
}
```

### Create Album

```php
use Revolution\Google\Photos\Facades\Photos;
use Google\Photos\Library\V1\PhotosLibraryResourceFactory;

public function createAlbum()
{
    $newAlbum = Photos::withToken(auth()->user()->refresh_token)
        ->createAlbum(PhotosLibraryResourceFactory::album('My New Album'));
    
    return [
        'id' => $newAlbum->getId(),
        'title' => $newAlbum->getTitle(),
        'url' => $newAlbum->getProductUrl(),
    ];
}
```

### List Albums

```php
use Revolution\Google\Photos\Facades\Photos;

public function listAlbums()
{
    $albums = Photos::withToken(auth()->user()->refresh_token)
        ->listAlbums();
    
    foreach ($albums as $album) {
        echo "Album: " . $album->getTitle() . "\n";
        echo "ID: " . $album->getId() . "\n";
        echo "Items: " . $album->getMediaItemsCount() . "\n";
    }
}
```

### Update Album Title

```php
use Revolution\Google\Photos\Facades\Photos;

public function updateAlbumTitle($albumId, $newTitle)
{
    $album = Photos::withToken(auth()->user()->refresh_token)
        ->updateAlbumTitle($albumId, $newTitle);
    
    return $album;
}
```

### Using with User Model Trait

Add the `PhotosLibrary` trait to your User model for cleaner syntax:

```php
use Revolution\Google\Photos\Traits\PhotosLibrary;

class User extends Authenticatable
{
    use PhotosLibrary;

    protected function tokenForPhotoLibrary(): array|string
    {
        return $this->refresh_token;
    }
}

// Usage with trait
$albums = $user->photos()->listAlbums();
$uploadToken = $user->photos()->upload($content, $filename);
```

### Google Photos Picker API

Use the Picker API to let users select existing photos from their Google Photos library:

```php
use Revolution\Google\Photos\Facades\Picker;
use Revolution\Google\Photos\Support\Token;

// Get access token from refresh token
$accessToken = Token::toAccessToken(auth()->user()->refresh_token);

// Create picker session
$picker = Picker::withToken($accessToken)->create();

// Redirect user to picker
return redirect($picker['pickerUri']);

// Later, check if user finished selecting
$session = Picker::withToken($accessToken)->get($picker['id']);
if ($session['mediaItemsSet']) {
    // Get selected media items
    $mediaItems = Picker::withToken($accessToken)->list($picker['id']);
}
```

## Advanced Usage

### PhotosLibraryClient Methods

This package delegates to the Google Photos Library PHP client and supports all its methods:

```php
use Revolution\Google\Photos\Facades\Photos;

// Get specific album
$album = Photos::withToken($token)->getAlbum($albumId);

// Search media items
$searchResults = Photos::withToken($token)->searchMediaItems($searchRequest);

// Add media items to album
$photos = Photos::withToken($token)->batchAddMediaItemsToAlbum($albumId, $mediaItemIds);
```

### PagedListResponse

Methods like `listMediaItems()` and `listAlbums()` return a `PagedListResponse` for handling large result sets:

```php
use Revolution\Google\Photos\Facades\Photos;
use Google\ApiCore\PagedListResponse;

$items = Photos::withToken($token)->listMediaItems();

// Iterate through all items (handles pagination automatically)
foreach ($items as $item) {
    echo $item->getBaseUrl() . "\n";
}

// Or access page information
$page = $items->getPage();
echo "Page token: " . $page->getNextPageToken();
```

### Error Handling

```php
use Revolution\Google\Photos\Facades\Photos;
use Google\ApiCore\ApiException;

try {
    $result = Photos::withToken($token)->upload($content, $filename);
} catch (ApiException $e) {
    Log::error('Google Photos API error: ' . $e->getMessage());
    return response()->json(['error' => 'Upload failed'], 500);
}
```

### Extending with Macros

You can extend the Photos facade with custom methods:

```php
// In AppServiceProvider::boot()
use Revolution\Google\Photos\Facades\Photos;

Photos::macro('customUpload', function ($file) {
    $uploadToken = $this->upload($file->getContent(), $file->getClientOriginalName());
    return $this->batchCreate([$uploadToken]);
});

// Usage
$result = Photos::withToken($token)->customUpload($file);
```

## FAQ

### Can I use a Service Account?

**No.** Service Account authentication is not supported by the Google Photos API. The API requires OAuth 2.0 user consent because it deals with personal photo data.

### Can I use an API Key?

**No.** API Key authentication is not supported by the Google Photos API. Only OAuth 2.0 authentication is available.

### Why only OAuth 2.0?

Google Photos API is designed exclusively for user-centric applications. Since photos are personal data, Google requires explicit user consent through OAuth 2.0. This ensures users maintain control over their photo data and can revoke access at any time.

### Can I access existing photos in a user's Google Photos library?

**No, not directly.** The Google Photos Library API only allows access to photos uploaded via your application. To work with existing user photos, you must use the **Google Photos Picker API** included in this package.

### Do I need review for production use?

If you're only uploading to your own account during development, no review is needed. However, for production use with other users' accounts, Google requires app review for sensitive scopes.

### What are the API limits?

Google Photos API has rate limits and quotas. See the [official documentation](https://developers.google.com/photos/library/guides/api-limits-quotas) for current limits.

## Related Packages

- **[laravel-google-sheets](https://github.com/invokable/laravel-google-sheets)** - Google Sheets API integration
- **[laravel-google-searchconsole](https://github.com/invokable/laravel-google-searchconsole)** - Google Search Console API integration

## Resources

- [Google Photos Library API Documentation](https://developers.google.com/photos/library/guides/get-started-library)
- [Google Photos Picker API Documentation](https://developers.google.com/photos/picker/guides/get-started-picker)
- [Laravel Socialite Documentation](https://laravel.com/docs/socialite)
- [Package Documentation](./docs/)

## License

MIT

---

**⚠️ Important Note**: This package can only access photos uploaded via your application. For accessing existing user photos, use the Google Photos Picker API functionality included in this package.  
