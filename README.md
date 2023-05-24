# Laravel Scout Qdrant Drivers

The Laravel Scout Qdrant Drivers package enables vector search capabilities within Laravel applications using Scout, Qdrant, and OpenAI. This package transforms your application's data into vectors using OpenAI, then indexes and makes them searchable using Qdrant, a powerful vector database management system.

> **Note**: This package is a work in progress and may not be ready for production use. However, with enough interest, I am dedicated to expanding and improving it.

## Prerequisites

- [Qdrant](https://qdrant.tech/documentation/) - This package requires Qdrant to be installed and running. While you can install Qdrant locally, we recommend using [Qdrant Cloud](https://qdrant.tech/documentation/cloud/) for a more scalable and robust solution. If you choose to use a local installation, you can pull and run the docker image using the commands below:

```bash
docker pull qdrant/qdrant
docker run -p 6333:6333 -v $(pwd)/database/qdrant:/qdrant/storage qdrant/qdrant
```

- [OpenAI for Laravel](https://github.com/openai-php/laravel) - This package also requires OpenAI to be set up for Laravel. You can publish the service provider with the command below:

```bash
php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"
```

Then, configure the OpenAI variables as per the instructions on the [OpenAI for Laravel page](https://github.com/openai-php/laravel).

## Installation

Install the package via Composer:

```bash
composer require gregpriday/laravel-scout-qdrant
```

Publish the config file with:

```bash
php artisan vendor:publish --tag="scout-qdrant-config"
```

## Configuration

After installation, you need to configure the `qdrant` settings in your `config/scout-qdrant.php` file, which is published by the installation process:

```php
return [
    'qdrant' => [
        'host' => env('QDRANT_HOST', 'http://localhost'),
        'key' => env('QDRANT_API_KEY', null),
    ]
];
```

The `QDRANT_HOST` key defines the location of your Qdrant service. If you are using Qdrant Cloud or a Docker container on a different server, update this value accordingly. The `QDRANT_API_KEY` key is for specifying your Qdrant API key if necessary.

For more information on configuring Qdrant, please refer to the [Qdrant documentation](https://qdrant.tech/documentation/install/).

In addition to the `qdrant` settings, ensure you have Scout configured to use the `qdrant` driver by setting the `SCOUT_DRIVER` in your `.env` file:

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

You can use the package just like you would use Laravel Scout, with the added benefit of vector-based searches which can provide more accurate and complex search results.

Additional usage instructions can be found in the [Laravel Scout documentation](https://laravel.com/docs/scout).

## Creating a Custom Vectorizer

To create a custom vectorizer, first, ensure you have a trained model with OpenAI. After that, you can create a new class that implements `GregPriday\ScoutQdrant\Vectorizer`. This interface requires a single method: `vectorize(string $text): array`.

For example:

```php
use GregPriday\LaravelScoutQdrant\Vectorizer\VectorizerInterface;

class MyVectorizer implements VectorizerInterface
{
    public function embedDocument(string $text): array
    {
        // Create a vector from the text using your own model
    }
    
    public function embedQuery(string $text): array
    {
        // Create a vector from the text using your own model
    }
}
```

After creating your custom vectorizer, you need to specify it in your `scout-qdrant.php` configuration file:

```php
return [
    // other config values...
    'vectorizer' => App\MyVectorizer::class,
];
```

Now your custom vectorizer will be used to create vectors for your Scout records.

## Testing

Run tests with:

```bash
composer test
```

## Changelog

For more information on what has changed recently, please see the [CHANGELOG](CHANGELOG.md).

## Contributing

Details on

how to contribute can be found in the [CONTRIBUTING](CONTRIBUTING.md) file.

## Security Vulnerabilities

For information on how to report a security vulnerability, please review [our security policy](../../security/policy).

## Credits

- [Greg Priday](https://github.com/gregpriday)
- [All Contributors](../../contributors)

## License

The Laravel Scout Qdrant Drivers is open-source software licensed under the [MIT license](LICENSE.md).
