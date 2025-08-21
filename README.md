# Noerd Framework

## 📦 Installation

```bash
# Copy the dist/noerd.css to your project
cp app-modules/noerd/dist/noerd.css public/css/

# Load it in your HTML/Blade template
<link rel="stylesheet" href="{{ asset('css/noerd.css') }}">
```

### Development environment

```bash
cd app-modules/noerd

# Ensure Node 22+ is available
nvm use 22

# Install dependencies
npm install

# Build CSS
npm run build-css

# Watch CSS during development
npm run watch
```

## 🔧 Technical Details

- **Tailwind CSS**: Version 4.0 (Beta)
- **Node.js**: Version 22+
- **Build Tool**: Vite 6
- **CSS Size**: ~56KB (compressed ~9.6KB)

## 📁 Files

- `dist/noerd.css` - Production-ready CSS file
- `dist/noerd-clean.css` - Same as above but without Tailwind header comment
- `resources/css/noerd.css` - Source CSS with Tailwind directives
- `tailwind.config.js` - Tailwind 4 configuration
- `vite.config.js` - Build configuration

## 🚀 Features

- **Compact**: Only used CSS classes are included
- **Responsive**: Mobile-first design
- **Modern Browsers**: Supports CSS Custom Properties
- **Dark Mode**: Automatic system theme detection
- **Focus Management**: Optimized accessibility

## 🔄 Updates

```bash
# Update dependencies
npm update

# Rebuild CSS
npm run build-css

# Reinstall to public folder
php artisan noerd:install --force
```

## 🛠️ Customization

1. Edit `resources/css/noerd.css` for style changes
2. Modify `tailwind.config.js` for theme adjustments
3. Run `npm run build-css` to compile changes
4. Run `php artisan noerd:install --force` to update public CSS

## 🚨 Troubleshooting

### PostCSS Plugin Error

If you get this error when importing `noerd.css` in another project:

```
[vite:css] [postcss] It looks like you're trying to use `tailwindcss` directly as a PostCSS plugin.
```

**Solution 1: Use the clean version**

Use `noerd-clean.css` which has the Tailwind header comment removed:

```bash
php artisan noerd:install --clean
```

**Solution 2: Exclude from PostCSS processing**

In your `vite.config.js`, exclude the noerd.css file from PostCSS:

```javascript
export default defineConfig({
  css: {
    postcss: {
      plugins: [
        // Your other PostCSS plugins
      ]
    }
  },
  // Exclude noerd.css from processing
  assetsInclude: ['**/*.css']
})
```

**Solution 3: Import as a static asset**

Instead of importing through CSS, link it directly in your HTML:

```html
<!-- In your HTML head -->
<link rel="stylesheet" href="/path/to/noerd.css">
```

**Solution 4: Copy to public directory**

Copy the file to your public assets and reference it directly:

```bash
cp node_modules/@nywerk/noerd/dist/noerd.css public/css/noerd.css
```

```html
<link rel="stylesheet" href="{{ asset('css/noerd.css') }}">
```

### Why this happens

The `noerd.css` file is fully compiled and doesn't contain any Tailwind directives. However, some build tools automatically process all CSS files through PostCSS, which can cause conflicts. The solutions above bypass this processing for the pre-compiled noerd.css file.
