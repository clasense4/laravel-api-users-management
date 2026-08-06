.PHONY: setup up down test quality migrate seed fresh docs docker-setup docker-test docker-coverage docker-quality

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

# Docker-based commands for reviewers (run everything inside containers)
docker-setup:
	@echo "Setting up Docker environment..."
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo "Created .env from .env.example"; \
		echo ""; \
		echo "⚠️  ACTION REQUIRED:"; \
		echo "Run: php artisan key:generate --show"; \
		echo "Then paste the key into .env as APP_KEY=base64:..."; \
		echo "Then run: make docker-setup again"; \
		exit 1; \
	fi
	@if ! grep -q "^APP_KEY=base64:" .env; then \
		echo "⚠️  APP_KEY not set in .env"; \
		echo "Run: php artisan key:generate --show"; \
		echo "Then paste the key into .env as APP_KEY=base64:..."; \
		exit 1; \
	fi
	docker compose up -d
	@echo "Waiting for services to be healthy..."
	@sleep 5
	docker compose exec app mkdir -p database
	docker compose exec app touch database/database.sqlite
	docker compose exec app php artisan migrate --seed --no-interaction
	@echo ""
	@echo "✅ Docker environment ready!"
	@echo "   API: http://localhost:8000"
	@echo "   Mail UI: http://localhost:8025"
	@echo ""
	@echo "Next steps:"
	@echo "  make docker-test      # Run tests"
	@echo "  make docker-coverage  # Generate coverage report"
	@echo "  make docker-quality   # Run pint + phpstan"

docker-test:
	docker compose exec app php artisan test --compact

docker-coverage:
	docker compose exec app php artisan test --coverage-html=coverage-report --compact
	@echo ""
	@echo "✅ Coverage report generated!"
	@echo "   Open: coverage-report/index.html"

docker-quality:
	docker compose exec app vendor/bin/pint --format agent
	docker compose exec app vendor/bin/phpstan analyse --memory-limit=256M
