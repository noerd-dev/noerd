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

## 🎨 Usage

### CSS Classes

The module provides pre-compiled CSS classes:

#### Forms
```html
<!-- Input field -->
<input class="noerd-input" type="text" placeholder="Enter text">

<!-- Text input -->
<input class="noerd-text-input" type="text">
```

#### Buttons
```html
<!-- Primary button -->
<button class="noerd-button-primary">Save</button>

<!-- Delete button -->
<button class="noerd-button-delete">Delete</button>
```

#### Navigation
```html
<!-- Active nav link -->
<a class="noerd-nav-link-active" href="#">Dashboard</a>

<!-- Inactive nav link -->
<a class="noerd-nav-link" href="#">Settings</a>
```

#### Tabs
```html
<!-- Active tab -->
<a class="noerd-tab noerd-tab-active" href="#">Active</a>

<!-- Inactive tab -->
<a class="noerd-tab" href="#">Inactive</a>
```

#### Components
```html
<!-- Dashboard card -->
<div class="noerd-dashboard-card">
    <span>Card</span>
</div>

<!-- Toggle -->
<div class="noerd-toggle noerd-toggle-enabled">
    <span class="noerd-toggle-slider noerd-toggle-slider-enabled"></span>
</div>

<!-- Box container -->
<div class="noerd-box">
    <p>Content</p>
</div>
```

#### Tables
```html
<table class="noerd-table">
    <tbody>
        <tr>
            <td>Cell 1</td>
            <td>Cell 2</td>
        </tr>
    </tbody>
</table>
```

#### Form Grid
```html
<div class="noerd-form-grid noerd-form-grid-12">
    <!-- 12-column grid structure -->
</div>
```

## 🌙 Dark Mode

The CSS includes automatic dark mode support for:
- Input fields (Slate color palette)
- Text colors (Zinc color palette)

## 🎨 Colors

The design uses a consistent color palette:
- **Primary**: Black (#000)
- **Background**: Gray tones (gray-50 to gray-900)
- **Accents**: Zinc color palette
- **Status**: Green/Red for success/error

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

## 🛠️ Customization

1. Edit `resources/css/noerd.css` for style changes
2. Modify `tailwind.config.js` for theme adjustments
3. Run `npm run build-css` to compile changes
