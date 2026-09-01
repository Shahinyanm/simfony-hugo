.PHONY: install up down migrate seed test

install: up
	docker compose exec php composer install
	$(MAKE) migrate
	$(MAKE) seed

up:
	docker compose up -d --build

down:
	docker compose down

migrate:
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
	docker compose exec php bin/console doctrine:database:create --if-not-exists --env=test
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction --env=test

seed:
	docker compose exec php bin/console app:demo:seed

test:
	docker compose exec -e APP_ENV=test php bin/phpunit
