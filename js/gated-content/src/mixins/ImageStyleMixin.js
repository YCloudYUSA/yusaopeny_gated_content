export const ImageStyleMixin = {
  methods: {
    getStyledUrl(image, imageStyle) {
      let styledUrl = '';
      Object.keys(image.image_style_uri).forEach(key => {
        if (!image.image_style_uri[imageStyle]) { return; }
        styledUrl = image.image_style_uri[imageStyle];
      });
      return styledUrl;
    },
  },
};
