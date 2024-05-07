
docker-up:  ## start docker stack in foreground
	docker compose up --build --abort-on-container-exit

# TODO: make recipes or perhaps use go-task?
# add user
# start local?
# make local dirs
# setup local db
# run tests
# install local requirements
# push production image


help:  ## get help
	@grep -E '^[a-zA-Z_-]+:.*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
