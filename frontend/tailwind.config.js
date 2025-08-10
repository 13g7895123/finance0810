/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./components/**/*.{js,vue,ts}",
    "./layouts/**/*.vue",
    "./pages/**/*.vue",
    "./plugins/**/*.{js,ts}",
    "./nuxt.config.{js,ts}",
    "./app.vue"
  ],
  safelist: [
    'ml-20',
    'ml-70', 
    'lg:ml-20',
    'lg:ml-70',
    'w-20',
    'w-70',
    // Primary color classes
    'bg-primary',
    'bg-primary-50',
    'bg-primary-100',
    'bg-primary-500',
    'bg-primary-600',
    'text-primary',
    'text-primary-400',
    'text-primary-500',
    'text-primary-600',
    'border-primary',
    'border-primary-500',
    'hover:bg-primary-600',
    'hover:text-primary-500',
    'hover:text-primary-600',
    'focus:ring-primary-500',
    'focus:border-primary-500'
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: {
          50: 'var(--primary-50)',
          100: 'var(--primary-100)',
          200: 'var(--primary-200)',
          300: 'var(--primary-300)',
          400: 'var(--primary-400)',
          500: 'var(--primary-500)',
          600: 'var(--primary-600)',
          700: 'var(--primary-700)',
          800: 'var(--primary-800)',
          900: 'var(--primary-900)',
          DEFAULT: 'var(--primary-500)'
        }
      },
      spacing: {
        '70': '280px',
        '20': '80px'
      },
      borderRadius: {
        'lg-custom': '16px'
      }
    },
  },
  plugins: [],
}