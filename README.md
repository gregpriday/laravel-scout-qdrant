# Laravel Scout Qdrant Drivers

The Laravel Scout Qdrant Drivers package introduces vector search capabilities within Laravel applications by leveraging Scout, Qdrant, and OpenAI. This package transforms your application's data into vectors using OpenAI, then indexes and makes them searchable using Qdrant, a powerful vector database management system.

> **Note**: This package is a work in progress and might not be ready for production use yet. However, with growing interest and support, the plan is to expand and improve it continually.

## Prerequisites

- [Qdrant](https://qdrant.tech/documentation/) - Qdrant installation is a prerequisite for this package. We recommend using [Qdrant Cloud](https://qdrant.tech/documentation/cloud/) for a more scalable and robust solution, but local installation is also possible. See Qdrant installation instructions [here](https://qdrant.tech/documentation/quick_start/#installation).

- [OpenAI for Laravel](https://github.com/openai-php/laravel) - OpenAI setup for Laravel is also necessary. Publish the service provider using:

```bash
php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"
```

Follow the instructions on the [OpenAI for Laravel page](https://github.com/openai-php/laravel) to configure OpenAI variables.

## Installation

Install the package via Composer:

```bash
composer require gregpriday/laravel-scout-qdrant
```

Add migrations with:

```bash
php artisan vendor:publish --tag="scout-qdrant-migrations"
```

Publish the configuration file with:

```bash
php artisan vendor:publish --tag="scout-qdrant-config"
```

## Configuration

After installation, you should configure the `qdrant` settings in your `config/scout-qdrant.php` file:

```php
return [
    'qdrant' => [
        'host' => env('QDRANT_HOST', 'http://localhost'),
        'key' => env('QDRANT_API_KEY', null),
        'storage' => env('QDRANT_STORAGE', 'database/qdrant'),
    ],
    'vectorizer' => env('QDRANT_VECTORIZER', 'openai'),
];
```

The `QDRANT_HOST` key defines the location of your Qdrant service. If you are using Qdrant Cloud or a Docker container on a different server, update this value accordingly. The `QDRANT_API_KEY` key is for specifying your Qdrant API key if necessary.

The `QDRANT_STORAGE` key indicates the location where Qdrant will store its files. By default, this is set to `database/qdrant`, but you can specify a different location depending on your setup.

The `QDRANT_VECTORIZER` key is used to define the vectorizer to be used. By default, it's set to 'openai'. If you have a custom vectorizer, you can specify it here.

For more details on configuring Qdrant, please refer to the [Qdrant documentation](https://qdrant.tech/documentation/install/).

Additionally, ensure Scout is configured to use the `qdrant` driver by setting the `SCOUT_DRIVER` in your `.env` file:

```env
SCOUT_DRIVER=qdrant
```

Your model should also include a `toSearchableArray` method that includes a `vector` key. This key gets converted into a vector using OpenAI:

```php
public function toSearchableArray()
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'vector' => $this->text,
        // more attributes...
    ];
}
```

## Usage

You can use the package just as you would use Laravel Scout, but with the added advantage of vector-based searches. This functionality offers more precise and complex search results.

For additional instructions on usage, please visit the [Laravel Scout documentation](https://laravel.com/docs/scout).

## Qdrant Docker Management Commands

Manage your Qdrant Docker container with the following commands:

### Install Qdrant

```bash
php artisan qdrant:install
```

This command pulls the Qdrant Docker image and checks whether Docker is installed on your machine. If it's not, the command provides instructions on installation.

### Start Qdrant

```bash
php artisan qdrant:start
```

Starts your Qdrant Docker container with the default port set to 6333, storage path as `database/qdrant`, and restart policy as `unless-stopped`. You can specify a different port, storage path or restart policy with the `--port`, `--storage`, and `--restart` options, respectively:

```bash
php artisan qdrant:start --port=6334 --storage=custom/qdrant --restart=always
```

### Restart Qdrant

```bash
php artisan qdrant:restart
```

Restarts your Qdrant Docker container. This command accepts the `--port`, `--storage`, and `--restart` options.

```bash
php artisan qdrant:restart --port=6334 --storage=custom/qdrant --restart=always
```

### Check Qdrant Status

```bash
php artisan qdrant:status
```

Provides the status of your Qdrant Docker container, including details like container ID, image, command, creation time, status, ports, and name.

### Stop Qdrant

```bash
php artisan qdrant:stop
```

Stops your Qdrant Docker container. Use `--kill` to kill the container instead of stopping it.

## Creating a Custom Vectorizer

To create a custom vectorizer, ensure you have a custom model or a third-party service. Then, create a new class that implements `GregPriday\ScoutQdrant\Vectorizer`. This interface requires a single method: `vectorize(string $text): array`.

Example:

```php
use GregPriday\LaravelScoutQdrant\Vectorizer\VectorizerInterface;

class MyVectorizer implements VectorizerInterface
{
    public function embedDocument(string $text): array
    {
        // Create a vector from the text using your model
    }
    
    public function embedQuery(string $text): array
    {
        // Create a vector from the text using your model
    }
}
```

Specify your custom vectorizer in your `scout-qdrant.php` configuration file:

```php
return [
    // other config values...
    'vectorizer' => App\MyVectorizer::class,
];
```

Now your custom vectorizer will be used to create vectors for your Scout records.

## Testing

Start Qdrant for testing with:

```bash
docker pull qdrant/qdrant
docker run -p 6333:6333 -v $(pwd)/database/qdrant:/qdrant/storage qdrant/qdrant
````

Execute tests with:

```bash
composer test
```

## Changelog

Visit the [CHANGELOG](CHANGELOG.md) for updates and changes.

## Contributing

Guidelines for contributing can be found in the [CONTRIBUTING](CONTRIBUTING.md) file.

## Security Vulnerabilities

If you discover a security vulnerability, please follow our [security policy](../../security/policy) to report it.

## Credits

- [Greg Priday](https://github.com/gregpriday)
- [All Contributors](../../contributors)

## License

The Laravel Scout Qdrant Drivers is open-source software licensed under the [MIT license](LICENSE.md).
