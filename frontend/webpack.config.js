const path = require('path');
const webpack = require('webpack');
const TerserPlugin = require('terser-webpack-plugin');

/**
 * Adapted from upstream Pterodactyl's webpack config (MIT — see vendor/LICENSE.md).
 *
 * Two things differ. The sources live under vendor/ rather than resources/scripts, and the
 * bundle is emitted to ../resources/dist under a fixed name, because the plugin publishes
 * that directory to public/plugins and references it by path rather than through a
 * manifest. The type-checker and bundle-analyzer plugins are dropped: this only has to
 * produce the artifact, not police the vendored tree.
 */
const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
    cache: true,
    target: 'web',
    mode: isProduction ? 'production' : 'development',
    devtool: isProduction ? false : 'eval-source-map',
    performance: { hints: false },
    entry: './integration/index.tsx',
    output: {
        path: path.join(__dirname, '../resources/dist'),
        filename: 'pterodactyl-ui.js',
        // Lazy chunks are fetched at runtime, so they must resolve against the published
        // location rather than the page the app happens to be mounted on.
        chunkFilename: 'chunks/[name].[chunkhash:8].js',
        publicPath: '/plugins/pterodactyl-ui/dist/',
        crossOriginLoading: 'anonymous',
    },
    module: {
        rules: [
            {
                test: /\.tsx?$/,
                exclude: /node_modules/,
                // Right-to-left: the patch loader edits source text before babel sees it.
                use: ['babel-loader', path.resolve(__dirname, 'integration/patch-loader.js')],
            },
            {
                test: /\.mjs$/,
                include: /node_modules/,
                type: 'javascript/auto',
            },
            {
                // This bundler predates optional chaining, and the WebAuthn helper ships
                // modern syntax in its published output, so it needs transpiling too. Its
                // own babel options are used rather than the project's, to keep the macro
                // and styled-components plugins off third-party code.
                test: /\.js$/,
                include: /node_modules[\\/]@simplewebauthn/,
                loader: 'babel-loader',
                options: {
                    babelrc: false,
                    configFile: false,
                    presets: [['@babel/preset-env', { targets: { browsers: ['> 0.5%', 'last 2 versions', 'not dead'] } }]],
                },
            },
            {
                test: /\.css$/,
                use: [
                    { loader: 'style-loader' },
                    {
                        loader: 'css-loader',
                        options: {
                            modules: {
                                auto: true,
                                localIdentName: isProduction ? '[name]_[hash:base64:8]' : '[path][name]__[local]',
                                localIdentContext: path.join(__dirname, 'vendor/components'),
                            },
                            sourceMap: !isProduction,
                            importLoaders: 1,
                        },
                    },
                    { loader: 'postcss-loader', options: { sourceMap: !isProduction } },
                ],
            },
            {
                test: /\.(png|jp(e?)g|gif|woff2?)$/,
                loader: 'file-loader',
                options: { name: 'assets/[name].[hash:8].[ext]' },
            },
            {
                test: /\.svg$/,
                loader: 'svg-url-loader',
            },
        ],
    },
    resolve: {
        extensions: ['.ts', '.tsx', '.js', '.json'],
        alias: {
            // Module overrides FIRST. Webpack applies the first alias whose key matches, so
            // these exact-match ($) entries have to precede the '@' prefix below — otherwise
            // '@' swallows every '@/...' request and the overrides silently never apply.
            // The route table gains plugin-contributed pages; history honours the mount path.
            '@/routers/routes$': path.join(__dirname, '/integration/routes.ts'),
            '@/components/history$': path.join(__dirname, '/integration/history.ts'),

            '@': path.join(__dirname, '/vendor'),
            '@definitions': path.join(__dirname, '/vendor/api/definitions'),
            '@feature': path.join(__dirname, '/vendor/components/server/features'),
            // The glue that adapts the vendored app to this panel.
            '@ui': path.join(__dirname, '/integration'),
            // Reaches the vendored tree from an override that replaces one of its modules.
            '@vendor': path.join(__dirname, '/vendor'),
        },
        symlinks: false,
    },
    externals: {
        // Chart.js pulls moment in optionally; it isn't used here.
        moment: 'moment',
    },
    plugins: [
        new webpack.EnvironmentPlugin({
            NODE_ENV: isProduction ? 'production' : 'development',
            DEBUG: !isProduction,
            WEBPACK_BUILD_HASH: Date.now().toString(16),
        }),
    ],
    optimization: {
        usedExports: true,
        sideEffects: false,
        runtimeChunk: false,
        removeEmptyChunks: true,
        minimize: isProduction,
        minimizer: [
            new TerserPlugin({
                cache: isProduction,
                parallel: true,
                extractComments: false,
                terserOptions: { mangle: true, output: { comments: false } },
            }),
        ],
    },
};
