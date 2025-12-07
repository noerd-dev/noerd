# noerd/noerd

noerd is a Laravel Livewire framework that offers a simple admin panel with lists and detailed views as well as tenant management.

Install the package
```
composer require noerd/noerd
php artisan noerd:install
```

Add an admin user (user must be registered first)
```
php artisan noerd:make-admin {userId}
```

To create a new app
```
php artisan noerd:create-tenant-app 
```

Assign apps to tenants
```
php artisan noerd:assign-apps-to-tenant  
```

