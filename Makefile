.PHONY: setup test coverage coverage-report quality migrate seed fresh docs docker-setup docker-test docker-coverage docker-quality

setup:
	cp -n .env.example .env || true
	composer install
	php artisan key:generate --no-interaction
	php artisan migrate --no-interaction
	php artisan db:seed --no-interaction
	@echo ""
	@echo "✅ Local environment ready!"
	@echo "   API: http://localhost:8000/api/docs"
	@echo ""
	@echo "Next steps:"
	@echo "  make test      # Run tests"
	@echo "  make coverage  # Generate coverage report"
	@echo "  make quality   # Run pint + phpstan"

test:
	@rm -f bootstrap/cache/*.php
	php artisan test --compact

coverage:
	@rm -f bootstrap/cache/*.php
	php artisan test --coverage --min=70 --compact

coverage-report:
	php artisan test --coverage-html=coverage-report --compact
	@echo ""
	@echo "✅ Coverage report generated!"
	@echo "   Open: coverage-report/index.html"

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
	docker compose build
	docker compose up -d
	@echo "Waiting for services to start..."
	@sleep 3
	docker compose exec app mkdir -p database
	docker compose exec app touch database/database.sqlite
	docker compose exec app php artisan migrate --seed --no-interaction
	@echo ""
	@echo "✅ Docker environment ready!"
	@echo "   API: http://localhost:8000/api/docs"
	@echo "   Mail UI: http://localhost:8025"
	@echo ""
	@echo "Next steps:"
	@echo "  make docker-test      # Run tests"
	@echo "  make docker-coverage  # Generate coverage report"
	@echo "  make docker-quality   # Run pint + phpstan"

docker-test:
	docker compose exec app sh -c 'rm -f bootstrap/cache/*.php && php artisan test --compact'

docker-coverage:
	docker compose exec app php artisan test --coverage-html=coverage-report --compact
	@echo ""
	@echo "✅ Coverage report generated!"
	@echo "   Open: coverage-report/index.html"

docker-quality:
	docker compose exec app vendor/bin/pint --format agent
	docker compose exec app vendor/bin/phpstan analyse --memory-limit=256M
