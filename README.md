<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/campaigns-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=campaigns-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/campaigns-for-laravel/main/art/hero.png" alt="Campaigns for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/campaigns-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/campaigns-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/campaigns-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/campaigns-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/campaigns-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/campaigns-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=campaigns-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Campaigns for Laravel

Send campaigns to many recipients through Laravel queues and job batches. Address them by email,
contact record or contact owner, deliver by email, a Laravel notification or your own job, and
keep campaigns in memory or in the database.

## Installation

Requires PHP 8.4 and Laravel 12 or 13.

```bash
composer require roundly-consulting/campaigns-for-laravel
php artisan vendor:publish --tag="campaigns-migrations" --tag="options-migrations" --tag="contacts-migrations"
php artisan migrate
```

Every campaign runs as a job batch, so the app needs Laravel's `job_batches` table
(`php artisan make:queue-batches-table` if it has none) and a running queue worker. To follow
progress across requests, keep campaigns in the database rather than the default per-process
memory: `CAMPAIGNS_STORE='RoundlyConsulting\Campaigns\Stores\DatabaseCampaignStore'`.

## Usage

Create, address and send a campaign through the facade, then follow its progress:

```php
use RoundlyConsulting\Campaigns\Facades\Campaigns;

$campaign = Campaigns::create('Buy today and spend less', '<p>Hello dear customer!</p>')
    ->from('no-reply@eshop.tld', 'Best E-Shop Ever')
    ->to(['john@doe.tld', 'jane@doe.tld'])     // emails, Contact records or HasContacts owners
    ->dispatch();                              // one queued delivery job per recipient

$progress = Campaigns::campaign($campaign->uuid)->progress();

$progress->percentage();                       // share delivered so far: 50.0
$progress->failed;                             // deliveries that failed
$progress->remaining();                        // recipients still to go

Campaigns::cancel($campaign->uuid);            // stop the rest
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/campaigns-for-laravel](https://roundly-consulting.com/open-source/docs/campaigns-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=campaigns-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=campaigns-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=campaigns-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
