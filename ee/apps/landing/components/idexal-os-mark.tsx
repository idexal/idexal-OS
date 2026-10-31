import Image from "next/image";

type Props = {
  className?: string;
};

export function IdexalOsMark(props: Props) {
  return (
    <Image
      src="/idexal-os-mark.svg"
      alt=""
      aria-hidden="true"
      className={props.className}
      width={377}
      height={380}
      unoptimized
    />
  );
}
