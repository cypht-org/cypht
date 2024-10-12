
DOCKERHUB_REPO=cypht/cypht

.PHONY: docker-up
docker-up:  ## start docker stack in foreground for development
	docker compose -f docker-compose.dev.yaml up --build || true # --abort-on-container-exit

.PHONY: docker-push
.ONESHELL:
docker-push:  ## build, tag, and push image to dockerhub. presumes you are logged in. run with a version like tag:1.2.3
	@[ "$(tag)" = "" ] && (echo "Tag required. Example tag=1.2.3" ; exit 1)
	@image=$(DOCKERHUB_REPO):$(tag)
	@echo "Building image $${image}"
	@docker buildx build . --platform linux/386,linux/amd64,linux/arm64,linux/arm/v7,linux/arm/v6 \
		-t $${image} -f docker/Dockerfile --push

.PHONY: dockerhub-push-readme
.ONESHELL:
dockerhub-push-readme:  ## upload readme to dockerhub
	docker pushrm --file docker/DOCKERHUB-README.md $(DOCKERHUB_REPO)

.PHONY: setup
.ONESHELL:
setup:  ## locally setup app and users. presumes env vars are set
	set -e
	echo "Installing dependencies"
	composer install
	echo "Creating tables and user"
	./scripts/setup_database.php
	echo "Creating directories and configs"
	./scripts/setup_system.sh

help:  ## get help
	@grep -E '^[a-zA-Z_-]+:.*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
