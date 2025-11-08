module.exports = {
	extends: [
		'@wordpress/eslint-plugin/recommended'
	],
	env: {
		browser: true,
		es6: true,
		jquery: true
	},
	globals: {
		wp: 'readonly',
		wc: 'readonly',
		specialRateShipping: 'readonly',
		ajaxurl: 'readonly'
	},
	rules: {
		'no-console': 'warn',
		'no-debugger': 'error',
		'no-unused-vars': 'error',
		'prefer-const': 'error',
		'no-var': 'error'
	},
	parserOptions: {
		ecmaVersion: 2020,
		sourceType: 'module'
	}
};