# Modern Admin Template

A comprehensive, responsive admin dashboard template built with Nuxt 3, featuring dark mode, internationalization, and real-time notifications.

## ✨ Features

### 🎨 Modern UI/UX
- **Responsive Design**: Works perfectly on desktop, tablet, and mobile devices
- **Dark/Light Theme**: System-aware theme switching with custom color schemes
- **Clean Interface**: Modern design with rounded corners and smooth animations
- **Customizable Colors**: Built-in color picker for primary theme customization

### 🗂️ Navigation System
- **Collapsible Sidebar**: Expandable/collapsible sidebar (280px ↔ 80px)
- **Two-level Menu**: Hierarchical navigation with parent and child items
- **Mobile Hamburger**: Mobile-responsive navigation menu
- **Breadcrumb Navigation**: Clear page hierarchy indication
- **Configurable Menu**: Admin can customize sidebar menu items via UI

### 🌍 Internationalization (i18n)
- **Multi-language Support**: English, Traditional Chinese (繁體中文), Japanese (日本語)
- **Dynamic Language Switch**: Real-time language switching in navbar
- **Complete Translation**: All UI elements fully translated
- **Browser Detection**: Automatic language detection with cookie persistence

### 🔔 Advanced Notification System
- **Real-time Updates**: Live notification simulation with priority levels
- **Rich Notification UI**: Unread counters, priority indicators, action buttons
- **Multiple Types**: System alerts, user activities, security warnings, reports
- **Interactive Management**: Mark as read, clear notifications, time-based display
- **Persistent State**: Notification state managed through Pinia store

### ⚙️ Customization Features
- **Theme Settings**: Complete theme customization with live preview
- **UI Configuration**: Toggle footer visibility and customize interface
- **Menu Management**: Add, remove, and organize sidebar menu items
- **Settings Persistence**: All preferences saved in local storage

## 🛠️ Technology Stack

- **Frontend Framework**: [Nuxt 3](https://nuxt.com/) - The Intuitive Vue Framework
- **UI Framework**: [Nuxt UI](https://ui.nuxt.com/) - Fully styled and customizable components
- **Styling**: [Tailwind CSS](https://tailwindcss.com/) - Utility-first CSS framework
- **Icons**: [Heroicons](https://heroicons.com/) - Beautiful hand-crafted SVG icons
- **State Management**: [Pinia](https://pinia.vuejs.org/) - The Vue Store
- **Internationalization**: [@nuxtjs/i18n](https://i18n.nuxtjs.org/) - i18n module for Nuxt
- **Build Tool**: [Vite](https://vitejs.dev/) - Next Generation Frontend Tooling
- **Language**: [TypeScript](https://www.typescriptlang.org/) - JavaScript with syntax for types

## 🚀 Quick Start

### Prerequisites
- Node.js 18+ 
- npm or yarn package manager

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/13g7895123/admin_template.git
   cd admin_template
   ```

2. **Install dependencies**
   ```bash
   npm install
   # or
   yarn install
   ```

3. **Start development server**
   ```bash
   npm run dev
   # or
   yarn dev
   ```

4. **Open your browser**
   Navigate to `http://localhost:3000`

### Build for Production

```bash
# Generate static files
npm run build

# Preview production build
npm run preview
```

## 📁 Project Structure

```
admin_template/
├── assets/
│   └── css/
│       └── main.css           # Global styles and CSS variables
├── components/
│   ├── AppBreadcrumb.vue      # Breadcrumb navigation component
│   ├── AppFootbar.vue         # Footer component with i18n
│   ├── AppNavbar.vue          # Top navigation with notifications
│   ├── AppSidebar.vue         # Collapsible sidebar navigation
│   └── SidebarMenuItem.vue    # Sidebar menu item component
├── layouts/
│   └── default.vue            # Main application layout
├── locales/
│   ├── en.json               # English translations
│   ├── ja.json               # Japanese translations
│   └── zh-TW.json            # Traditional Chinese translations
├── pages/
│   ├── dashboard/
│   │   └── analytics.vue      # Analytics dashboard page
│   ├── help/
│   │   └── index.vue         # Help center main page
│   ├── settings/
│   │   ├── index.vue         # Settings overview page
│   │   ├── theme.vue         # Theme customization page
│   │   └── ui.vue            # UI configuration page
│   └── index.vue             # Homepage dashboard
├── stores/
│   ├── notifications.js      # Notification management store
│   ├── settings.js           # UI settings and preferences store
│   ├── sidebar.js            # Sidebar state management store
│   └── theme.js              # Theme customization store
├── app.vue                   # Root Vue component
├── nuxt.config.ts            # Nuxt configuration
├── package.json              # Project dependencies and scripts
└── tailwind.config.js        # Tailwind CSS configuration
```

## 🎯 Key Components

### Sidebar Navigation
- **Responsive Behavior**: Auto-collapses on mobile, toggleable on desktop
- **Menu Hierarchy**: Supports nested menu items with expand/collapse
- **State Persistence**: Remembers collapsed/expanded state
- **Custom Configuration**: Menu items configurable through admin interface

### Notification System
- **Priority Levels**: High (red), Medium (yellow), Low (blue)
- **Notification Types**: System, User, Security, Reports
- **Real-time Simulation**: Automatic notification generation for demo
- **Interactive UI**: Click to mark as read, bulk actions available

### Theme System
- **Dark/Light Modes**: System preference aware with manual override
- **Custom Colors**: Primary color customization with live preview
- **CSS Variables**: Efficient theme switching using CSS custom properties

### Internationalization
- **Language Support**: EN, ZH-TW, JA with extensible architecture
- **Lazy Loading**: Languages loaded on-demand for performance
- **Fallback System**: Graceful handling of missing translations

## ⚙️ Configuration

### Adding New Languages

1. Create translation file in `locales/` directory
2. Add locale configuration in `nuxt.config.ts`:
   ```typescript
   i18n: {
     locales: [
       // ... existing locales
       {
         code: 'your-locale',
         name: 'Language Name',
         flag: '🏁',
         file: 'your-locale.json'
       }
     ]
   }
   ```

### Customizing Theme Colors

1. Navigate to Settings → Theme Settings
2. Choose from predefined colors or use custom color picker
3. Changes apply immediately with live preview

### Configuring Sidebar Menu

1. Go to Settings → UI Settings
2. Add/remove menu items and submenu items
3. Choose appropriate icons from dropdown
4. Save settings to persist changes

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- [Nuxt.js](https://nuxt.com/) for the amazing framework
- [Tailwind CSS](https://tailwindcss.com/) for the utility-first CSS framework
- [Heroicons](https://heroicons.com/) for the beautiful icon set
- [Pinia](https://pinia.vuejs.org/) for state management

## 📞 Support

If you have any questions or need support, please:
- Create an issue on [GitHub](https://github.com/13g7895123/admin_template/issues)
- Check the [TIPS.md](TIPS.md) file for development tips and original requirements

---

Built with ❤️ using Nuxt 3 and modern web technologies.