import formsPlugin from '@tailwindcss/forms';

export default {
    content: ['./resources/**/*.blade.php', './resources/**/*.js'],
    theme: {
        extend: {},
    },
    plugins: [formsPlugin],
};
