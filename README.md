# Noerd UI Module

A modular UI framework for Laravel applications with pre-compiled Tailwind CSS 4 styles.

## 📦 Installation

### As standalone package

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
- `dist/noerd.js` - Minimal JS file (build process only)
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
```
