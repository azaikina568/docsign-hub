import pluginVue from 'eslint-plugin-vue'
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript'
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting'

// Flat-config по конвенции create-vue: vue/recommended + typescript-eslint/recommended,
// форматирование отдаём Prettier (skipFormatting гасит конфликтующие правила стиля).
export default defineConfigWithVueTs(
  {
    name: 'app/files',
    files: ['**/*.{ts,mts,vue}'],
  },
  {
    name: 'app/ignores',
    ignores: ['dist/**', 'coverage/**', 'node_modules/**'],
  },
  pluginVue.configs['flat/recommended'],
  vueTsConfigs.recommended,
  {
    name: 'app/rules',
    rules: {
      // С TS-типизированными пропсами через `defineProps<T>()` дефолты необязательны — опциональность задаёт тип.
      'vue/require-default-prop': 'off',
      // App — легитимное корневое имя одностраничного приложения.
      'vue/multi-word-component-names': ['error', { ignores: ['App'] }],
    },
  },
  skipFormatting,
)
