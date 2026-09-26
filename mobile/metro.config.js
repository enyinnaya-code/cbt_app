// Learn more https://docs.expo.dev/guides/customizing-metro/
const { getDefaultConfig } = require('expo/metro-config');

const config = getDefaultConfig(__dirname);

// The pack bundled inside the app ("starter" questions that work before anything is downloaded) is shipped as a
// plain data file, not compiled into the JavaScript, so it costs nothing at start-up.
config.resolver.assetExts.push('pack');

module.exports = config;
