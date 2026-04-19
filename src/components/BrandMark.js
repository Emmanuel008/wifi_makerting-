import React from 'react';

const SRC = `${process.env.PUBLIC_URL}/icon.jpeg`;

export default function BrandMark() {
  return (
    <img
      className="brandMark"
      src={SRC}
      alt=""
      width={34}
      height={34}
      decoding="async"
      aria-hidden="true"
    />
  );
}
