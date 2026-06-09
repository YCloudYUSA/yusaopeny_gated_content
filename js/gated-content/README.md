# gated-content

## Project setup
```
npm install
```

### For debugging and in-browser development

- Install the [Vue Devtools](https://devtools.vuejs.org/guide/installation.html) browser extension.
- Edit `main.js` and add this line after the `import`s:
  ```js
  Vue.config.devtools = true;
  ```
- Run `npm run dev` (or `fin exec npm run dev` if you are using Docksal).
- Load your site in the browser and open the Inspector to see the Vue Devtools panel.
  - If the [devtools don't show up](https://devtools.vuejs.org/guide/faq.html#the-vue-devtools-don-t-show-up) you may need to close the inspector, reload the page, and try again.

### Compiles and minifies for production
```
npm run build
```

### Lints and fixes files
```
npm run lint
```

### Customize configuration
See [Configuration Reference](https://cli.vuejs.org/config/).
