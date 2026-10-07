# Task manager

A small PHP 8 task manager with persistent JSON storage, completion toggles,
deletion, filters, and CSRF protection. No Composer packages or database needed.

Run from this directory:

```sh
php -S 127.0.0.1:8080 -t public
```

Open http://127.0.0.1:8080 in your browser. The PHP process needs write access
to `data/`, which is created automatically outside the public document root.
This app is for personal local use; it has no user authentication.

In this cloud workspace, PHP is available at `/workspace/php-runtime/bin/php`.
Use that path in place of `php` in the command above, with
`-d session.save_path=/tmp` before `-S`.
