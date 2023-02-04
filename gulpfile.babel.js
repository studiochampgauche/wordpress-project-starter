'use strict';
import gulp from 'gulp';
import dartSass from 'sass';
import gulpSass from 'gulp-sass';
import uglify from 'gulp-uglify';
import concat from 'gulp-concat';
import imagemin from 'gulp-image';
import fontmin from 'gulp-fontmin';
const sass = gulpSass(dartSass);

const WP = true;
const THEME_NAME = 'the-theme';
const DIST_PATH = WP ? './dist/wp-content/themes/' + THEME_NAME : './dist';
const SRC_PATH = './src';


gulp.task('wp-config', () =>
    gulp.src([SRC_PATH + '/wp-config.php'])
    .pipe(gulp.dest('./dist'))
);

gulp.task('extensions', () =>
    gulp.src([SRC_PATH + '/extensions/**/*'])
    .pipe(gulp.dest('./dist/wp-content/plugins'))
);

gulp.task('template', () =>
    gulp.src([SRC_PATH + '/template/**/*'])
    .pipe(gulp.dest(DIST_PATH))
);

gulp.task('scss', () =>
    gulp.src([
        SRC_PATH + '/scss/**/*.scss',
        '!' + SRC_PATH + '/scss/inc/**/*.scss',
    ])
    .pipe(sass({outputStyle: 'compressed'}).on('error', sass.logError))
    .pipe(gulp.dest(DIST_PATH + '/assets/css'))
);
 
gulp.task('js', () =>
    gulp.src([
        SRC_PATH + '/js/**/*.js',
        '!' + SRC_PATH + '/js/inc/**/*.js',
    ])
    .pipe(uglify())
    .pipe(gulp.dest(DIST_PATH + '/assets/js'))
);

gulp.task('js-includes', () =>
    gulp.src([
        SRC_PATH + '/js/inc/**/*.js'
    ])
    .pipe(uglify())
    .pipe(gulp.dest(DIST_PATH + '/assets/js'))
);

gulp.task('images', () =>
    gulp.src([SRC_PATH + '/images/**/*'], {allowEmpty: true})
    .pipe(imagemin())
    .pipe(gulp.dest(DIST_PATH + '/assets/images'))
);

gulp.task('fonts', () =>
    gulp.src([SRC_PATH + '/fonts/**/*.ttf'], {allowEmpty: true})
    .pipe(fontmin())
    .pipe(gulp.dest(DIST_PATH + '/assets/fonts'))
);


gulp.task('default', function () {

    gulp.watch([SRC_PATH + '/wp-config.php'], gulp.series('wp-config'))

    gulp.watch([SRC_PATH + '/template/**/*'], gulp.series('template'))

    gulp.watch([
        SRC_PATH + '/scss/**/*.scss',
        '!' + SRC_PATH + '/scss/inc/**/*.scss',
    ], gulp.series('scss'))
  
    gulp.watch([
        SRC_PATH + '/js/**/*.js',
        '!' + SRC_PATH + '/js/inc/**/*.js',
    ], gulp.series('js'))

});


gulp.task('optimize', gulp.series(
    'images',
    'fonts',
));

gulp.task('includes', gulp.series(
    'js-includes',
));

gulp.task('prod', gulp.series(
    'wp-config',
    'extensions',
    'includes',
    'optimize',
    'template',
    'scss',
    'js',
));

gulp.task('prod-watch', gulp.series(
    'prod',
    'default',
));