module.exports = function(grunt) {

    var es2015Preset = require('babel-preset-env');
    var reactPreset = require('babel-preset-react');

    function stripPrefixForTemplates(filePath) {
        /**
         * Strip '../../public/js/cat_source/templates/'
         * from template identifiers.
         */
        var dirsToStrip = 3 ;
        var strippedPath = filePath.split('/')
            .splice( dirsToStrip ).join('/')
            .replace('.hbs', '') ;

        return strippedPath ;
    }

    grunt.initConfig( {
        handlebars: {
            options: {
                namespace: 'MateCat.Templates',
                processPartialName: stripPrefixForTemplates,
                processName: stripPrefixForTemplates
            },
            all: {
                src : [
                    'static/src/templates/review_improved/segment_buttons.hbs'
                ],
                dest : 'static/build/js/templates.js'
            }
        },
        browserify: {
            components: {
                options: {
                    transform: [
                        [ 'babelify', { presets: [ es2015Preset, reactPreset ] } ]
                    ],
                    browserifyOptions: {
                        paths: [ __dirname + '/node_modules' ]
                    }
                },
                src: [
                    'static/src/js/components/review_improved/*.js',
                    'static/src/js/components/*.js',
                ],
                dest:  'static/build/js/ebay-components.js'
            },
            qaReportsVersions: {
                options: {
                    transform: [
                        [ 'babelify', { presets: [ es2015Preset, reactPreset ] } ]
                    ],
                    browserifyOptions: {
                        paths: [ __dirname + '/node_modules' ]
                    }
                },
                src: [
                    'static/src/js/quality_report/review_improved.qa_report.js',
                ],
                dest: 'static/build/js/qa-report-improved.js'
            },
        },
        concat: {
            app: {
                options: {
                    sourceMap: false,
                },
                src: [
                    'static/src/js/libs/handlebars.runtime-v4.0.5.js',
                    'static/build/js/templates.js',
                    'static/src/js/review_improved/review_improved.js',
                    'static/src/js/review_improved/review_improved.common_extensions.js',
                    'static/src/js/review_improved/review_improved.common_events.js',
                    'static/src/js/review_improved/review_improved.translate_extensions.js',
                    'static/src/js/review_improved/review_improved.translate_events.js',
                    'static/src/js/review_improved/review_improved.review_extension.js',
                    'static/src/js/review_improved/review_improved.review_events.js',
                    'static/src/js/review_improved/review_improved.rangy-hack.js',
                ],
                dest: 'static/build/js/ebay-core.js'
            },
        },
        sass: {
            app: {
                options : {
                    sourceMap : false,
                },
                src: [
                    'static/src/css/sass/review_improved.scss'
                ],
                dest: 'static/build/css/review_improved.css'
            },
        }
    });

    grunt.loadNpmTasks('grunt-browserify');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-handlebars');

    grunt.loadNpmTasks('grunt-sass');

    // Define your tasks here
    grunt.registerTask('default', ['bundle:js']);

    grunt.registerTask('bundle:js', [
        'handlebars',
        'browserify:components',
        'browserify:qaReportsVersions',
        'concat',
        'sass:app'
    ]);



};
