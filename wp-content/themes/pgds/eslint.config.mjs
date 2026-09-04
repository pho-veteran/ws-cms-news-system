// ============================================================================
// ESLint flat config
// ----------------------------------------------------------------------------
// ESLint 10 removed support for .eslintrc.json, so the previous eslintrc file
// was replaced by this flat config. The rule set is carried over unchanged:
// eslint:recommended, plus a relaxed no-unused-vars and no-console off.
//
// Browser globals are declared explicitly rather than pulling in the `globals`
// package — src/js only touches these three, and the theme deliberately keeps
// its dependency list short. Add an entry here when new platform APIs are used.
// ============================================================================

import js from '@eslint/js';

export default [
  {
    ignores: ['assets/dist/**'],
  },
  js.configs.recommended,
  {
    files: ['src/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 2020,
      sourceType: 'module',
      globals: {
        CSS: 'readonly',
        clearInterval: 'readonly',
        document: 'readonly',
        setInterval: 'readonly',
        window: 'readonly',
        URLSearchParams: 'readonly',
      },
    },
    rules: {
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
      'no-console': 'off',
    },
  },
];
