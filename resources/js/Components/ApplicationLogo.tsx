import { ImgHTMLAttributes } from 'react';

export default function ApplicationLogo(props: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src="/img/logo_ariCare_logo.png" alt="AriCare" {...props} />;
}
