# Action Website

A private project to build the official website for **ACTION**, a Data Science competition organized by our major.


## 🚀 Project Description

This is a web application developed to support **ACTION**, a data science competition. The site will serve as a platform for:
- Competition information
- Registration and announcements
- Participant management and results


## 🛠️ Tech Stack

- **Backend**: 
    - **Laravel** 
    - **PHP**
- **Frontend**: 
    - **React.js** for authenticate pages and
    - **Vite** as the build tool
    - **Tailwind CSS** for utility-first styling
    - **Alpine.js** for small, use in static Page that could be seen by robots.
- **Full-stack Bridge**:
    - **Inertia.js** to connect the Laravel backend with the React frontend seamlessly.
- **Database**:
    - **MySQL**
- **Development & Tooling**:
    - **Bun** as the JavaScript runtime and package manager.
    - **Composer** for PHP package management.


## 🎯 Project Goal

To develop a responsive and informative website for **ACTION**, ensuring a smooth and user-friendly experience for participants, organizers, and visitors.


## 👥 Team Members

- **kiuyha**
- **dika**


## 📌 Status

> 🛠️ Currently in development.


## 📦 Installing

1. clone the repo using
```
git clone https://github.com/kiuyha/Action.git
```
2. install the neccesary laravel dependencies
```
composer install
```
3. install the neccesary react dependencies
```
npm install
```
4. migrate the database
```
php artisan migrate
```
5. seed the database
```
php artisan db:seed
```
4. run the react using for development
```
bun run dev
```
or run this for production
```
bun run build
```
5. run the website
```
php artisan serve
```

6. run the queue
```
php artisan queue:listen
```

7. run the test
```
php artisan test
```
or using parallel test
```
php artisan test -p
```

## Deployment Notes
- Fetch the new build using 
```
chmod +x deploy.sh
./deploy.sh
```
- Make sure to change the `APP_URL` in the `.env` file.
- Make sure to change the database credentials in the `.env` file.
- Make sure to change config in turnstile and google auth console
- Make sure to run `php artisan optimize` before deployment.

## 📎 Notes

- Make sure check custom function in app/Support/helpers.php
- This repository is private and only accessible by authorized contributors.
- All contributions, ideas, and issues should be shared through GitHub or team discussions.