.PHONY: setup up down test quality migrate seed fresh

setup:
	cp -n .env.example .env || true
	composer install
	php artisan key:generate --no-interaction
	php artisan migrate --no-interaction
	@echo "Setup complete. Run 'make seed' to load demo data."

up:
	docker compose up -d
	docker compose exec app php artisan migrate --no-interaction

down:
	docker compose down

test:
	php artisan test --compact

coverage:
	php artisan test --coverage --min=70 --compact

coverage:
	php artisan test --coverage --min=70 --compact

quality:
	vendor/bin/pint --format agent
	vendor/bin/phpstan analyse --memory-limit=256M

migrate:
	php artisan migrate --no-interaction

seed:
	php artisan db:seed --no-interaction

fresh:
	php artisan migrate:fresh --seed --no-interaction

docs:
	php artisan scribe:generate
