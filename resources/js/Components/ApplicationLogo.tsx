import { ImgHTMLAttributes } from 'react';

export default function ApplicationLogo(props: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src="/img/ariCRM_logo.png" alt="AriCRM" {...props} />;
}
