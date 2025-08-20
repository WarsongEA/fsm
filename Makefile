# Makefile for FSM Project - Basic Docker Setup
SHELL := /bin/bash
.DEFAULT_GOAL := help
.PHONY: help

# Terminal colors
GREEN := \033[0;32m
YELLOW := \033[1;33m
BLUE := \033[0;34m
CYAN := \033[0;36m
NC := \033[0m # No Color

# Docker configuration
DOCKER_COMPOSE := docker compose
DOCKER_COMPOSE_FILE := docker/docker-compose.yml
CONTAINER := openswoole

##@ General

help: ## Display this help message
	@echo -e ""
	@echo -e "${CYAN}FSM Docker Management${NC}"
	@echo -e "${CYAN}=====================${NC}"
	@echo -e ""
	@echo -e "Usage:"
	@echo -e "  make ${GREEN}<target>${NC}"
	@echo -e ""
	@echo -e "${YELLOW}Docker Management${NC}"
	@echo -e "  ${GREEN}build${NC}           Build Docker containers"
	@echo -e "  ${GREEN}up${NC}              Start Docker containers"
	@echo -e "  ${GREEN}down${NC}            Stop Docker containers"
	@echo -e "  ${GREEN}restart${NC}         Restart Docker containers"
	@echo -e "  ${GREEN}logs${NC}            Show Docker logs"
	@echo -e "  ${GREEN}ps${NC}              Show container status"
	@echo -e "  ${GREEN}shell${NC}           Access container shell"
	@echo -e "  ${GREEN}clean${NC}           Clean Docker resources"
	@echo -e ""

##@ Docker Management

.PHONY: build
build: ## Build Docker containers
	@echo -e "${BLUE}Building Docker containers...${NC}"
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) build
	@echo -e "${GREEN}Build complete!${NC}"

.PHONY: up
up: ## Start Docker containers
	@echo -e "${BLUE}Starting Docker containers...${NC}"
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) up -d
	@echo -e "${GREEN}Containers started!${NC}"

.PHONY: down
down: ## Stop Docker containers
	@echo -e "${BLUE}Stopping Docker containers...${NC}"
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) down --remove-orphans
	@echo -e "${GREEN}Containers stopped!${NC}"

.PHONY: restart
restart: down up ## Restart Docker containers

.PHONY: logs
logs: ## Show Docker logs
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) logs -f

.PHONY: ps
ps: ## Show container status
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) ps

.PHONY: shell
shell: ## Access container shell
	@echo -e "${BLUE}Opening container shell...${NC}"
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) exec $(CONTAINER) bash

.PHONY: clean
clean: ## Clean Docker resources
	@echo -e "${BLUE}Cleaning Docker resources...${NC}"
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) down -v --remove-orphans
	@echo -e "${GREEN}Docker cleaned!${NC}"