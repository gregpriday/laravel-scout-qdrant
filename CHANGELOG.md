# Changelog

## [0.2.0] - 2023-05-29

- Added `qdrant:update` command to update Qdrant Docker container.
- Added `--restart` argument to `qdrant:start` and `qdrant:restart` commands for specifying Docker container restart policy.
- Added `--kill` argument to `qdrant:stop` command to force stop the container.
- Renamed `qdrant:terminate` command to `qdrant:stop` with an alias for backwards compatibility.
- Updated README with Qdrant installation instructions and usage updates.

## [0.1.0] - 2023-05-27

- Initial release of Laravel Scout Qdrant Drivers.
- Integration of Scout, Qdrant, and OpenAI to provide vector search capabilities in Laravel.
- Artisan commands for managing Qdrant Docker container - installation, start, restart, status check, and termination.
- Configurations for Qdrant including host, key, storage, and vectorizer.
