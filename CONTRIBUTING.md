# How to Contribute

## Pull Requests

1. Fork the Slim Skeleton repository
2. Create a new branch for each feature or improvement
3. Send a pull request from each feature branch to the **4.x** branch

It is very important to separate new features or improvements into separate feature branches, and to send a
pull request for each branch. This allows us to review and pull in new features or improvements individually.

## Style Guide

All pull requests must adhere to the [PSR-12 standard](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-12-extended-coding-style-guide.md).

## Image Standards

Image uploads should use the following formats and quality settings:

| Type    | Quality | Notes                                  |
|---------|---------|----------------------------------------|
| Logo    | 80      | Saved losslessly when using PNG        |
| Sticker | 90      |                                        |
| Photo   | 70      |                                        |

PNG images are stored without quality loss, while JPEG and WEBP images use the given quality values. Use the constants defined in `ImageUploadService` (`QUALITY_LOGO`, `QUALITY_STICKER`, `QUALITY_PHOTO`) to ensure consistent quality across the project.
