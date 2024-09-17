## Introduction
This a base for Debricked's backend home task. It provides a Symfony skeleton and a Docker environment with a few handy 
services:

- RabbitMQ
- MySQL (available locally at 3307, between Docker services at 3306)
- MailHog (UI available locally at 8025)
- PHP
- Nginx (available locally at 8888, your API endpoints will accessible through here)

See .env for working credentials for RabbitMQ, MySQL and MailHog.

A few notes:
- By default, emails sent through Symfony Mailer will be sent to MailHog, regardless of recipient.

## How to use the Docker environment
### Starting the environment
`docker compose up`

### Stopping the environment
`docker compose down`

### Running PHP based commands
You can access the PHP environment's shell by executing `docker compose exec php bash` (make sure the environment is up 
and running before, or the command will fail) in root folder.

We recommend that you always use the PHP container's shell whenever you execute PHP, such as when installing and 
requiring new composer dependencies.

Postman Collection link: https://speeding-trinity-190172.postman.co/workspace/92ca66d7-fa22-480c-869c-577ab34e87c5/collection/23170706-a7a39a2f-06d0-4934-a019-d602b4068dd9/overview?action=share&creator=23170706&active-environment=23170706-1db2e77d-dbc3-408a-b540-0fe3f3cc5cef&action_performed=login&action_performed=google_login&workspaceOnboarding=show