# SSH Telegram Bot

Tutorial: https://blog.learningdollars.com

## Requirements
1. [Telegram](https://telegram.org/) Account.
2. Linux OS (feel free to use any OS).
3. [Git](https://git-scm.com/).
4. [PHP](https://www.php.net/).
5. [Ngrok](https://ngrok.com/).
6. SSH server
7. Redis Server
8. [Predis](https://github.com/nrk/predis)
9. [Phpseclib](https://github.com/phpseclib/phpseclib)


## Getting Started

1. Clone this repository and `cd` into it.
2. cd into the application/ directory and execute;

- `composer install`
- `export REDIS_HOST=YOUR-REDIS-HOST`
- `export REDIS_PORT=YOUR-REDIS-PORT`
3. Create a new chatbot via [BotFather](https://telegram.me/BotFather) and store the newly created access token in your OS environment variable by running: `export TELEGRAM_ACCESS_TOKEN=YOUR-ACCESS-TOKEN`

## Usage
Navigate to the project directory in your terminal and start your PHP local web server

```bash
php -S localhost:5000
```
Open a new terminal, Navigate to the project directory and start your Ngrok Server

```bash
ngrok http 5000
```
To set the webhook URL to your chatbot, run this in your terminal. 
Note: Replace YOUR_NGROK_URL with your Ngrok server URL.

```bash
curl https://api.telegram.org/bot$TELEGRAM_ACCESS_TOKEN/setWebhook?url=https://YOUR_NGROK_URL/index.php/flow/webhook
```
## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## Licence

[MIT](https://opensource.org/licenses/MIT)

