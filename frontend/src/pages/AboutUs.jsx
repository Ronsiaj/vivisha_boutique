import React from 'react';
import PageHero from '../components/PageHero.jsx';
import about_image from "../../assets/images/about_image.jpg";

const AboutUs = () => {
  return (
    <div className="boutique-page-wrapper">
      <PageHero
        title="About Us"
        breadcrumb="About Us"
        description="Discover our journey, passion for craftsmanship, and timeless fashion for the modern woman."
      />

      <div className="boutique-page-container about-page-container">
        <section className="about-split-layout">
          <div className="about-image-side">
            <div className="about-image-frame">
              <img src={about_image} alt="Vivisha Boutique Collection" />
            </div>
          </div>

          <div className="about-text-side">
            <h2 className="about-heading">Redefining Elegance</h2>
            <p className="section-text">
              Welcome to Boutique, where timeless elegance meets contemporary fashion. We bring together thoughtfully curated sarees, kurtis, gowns, western wear, jewellery, and accessories designed to make every occasion feel special.
            </p>

            <p className="section-text">
              Our collections blend traditional charm with modern style, offering beautiful designs, quality fabrics, and effortless elegance for every woman. From everyday favourites to statement pieces for celebrations, we believe fashion should help you express your own unique style.
            </p>

            <div className="about-emphasis-card">
              <p className="emphasis-text">
                Discover your style. Embrace your elegance. Make every look yours.
              </p>
            </div>
          </div>
        </section>
      </div>
    </div>
  );
};

export default AboutUs;
