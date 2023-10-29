# Learn Web Push

## Environment

-   macOS Sonoma 14.0
-   Safari 17.0
-   Google Chrome 118.0.5993.117
-   PHP 8.0.30

## Usage

1.  Generate keys

    ```sh
    make keygen
    ```

2.  Start docker container

    ```sh
    docker compose up
    ```

3.  Open localhost in browser

    ```sh
    open http://localhost
    ```

4.  Click `Subscribe and push`

    An alert will appear asking 'The website "localhost" would like to send you notifications in Notification Center'.

5.  Click `Allow`

    A banner with the message "Notification is pushed." is displayed.

6.  Click the notification banner

    A tab (<http://localhost>) opens in the browser (Does not work in Safari).
