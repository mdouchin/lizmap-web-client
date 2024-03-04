CREATE TABLE IF NOT EXISTS presentation (
    id int GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    repository text NOT NULL,
    project text NOT NULL,
    title text NOT NULL,
    description text,
    footer text,
    author text NOT NULL,
    published boolean NOT NULL DEFAULT False,
    granted_groups text
);

COMMENT ON TABLE presentation IS 'Stores the presentations created for Lizmap maps.';
COMMENT ON COLUMN presentation.id IS 'Automatic unique ID';
COMMENT ON COLUMN presentation.repository IS 'Lizmap repository key';
COMMENT ON COLUMN presentation.project IS 'Lizmap project key';
COMMENT ON COLUMN presentation.title IS 'Presentation title';
COMMENT ON COLUMN presentation.description IS 'Description of the presentation';
COMMENT ON COLUMN presentation.footer IS 'Optional footer visible in all pages';
COMMENT ON COLUMN presentation.author IS 'Author (Lizmap login) of the presentation';
COMMENT ON COLUMN presentation.published IS 'True if the presentation is published, i.e. visible for the users';
COMMENT ON COLUMN presentation.granted_groups IS 'List of user groups that can see this presentation: a list of groups identifier separated by coma. Ex: admins, others';


CREATE TABLE IF NOT EXISTS presentation_page (
    id int GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    presentation_id integer NOT NULL,
    title text NOT NULL,
    description text,
    page_order smallint NOT NULL,
    model text NOT NULL,
    background_image text,
    background_color text,
    map_extent text,
    tree_state json,
    illustration_type text,
    illustration_media text,
    illustration_url text,
    illustration_feature json,
    CONSTRAINT fk_presentation
        FOREIGN KEY(presentation_id)
        REFERENCES presentation (id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

COMMENT ON TABLE presentation_page IS 'Caracteristics of a presentation page';
COMMENT ON COLUMN presentation_page.id IS 'Automatic unique ID';
COMMENT ON COLUMN presentation_page.presentation_id IS 'Identifier of the parent presentation (foreign key)';
COMMENT ON COLUMN presentation_page.title IS 'Title of the page';
COMMENT ON COLUMN presentation_page.description IS 'Description of the page';
COMMENT ON COLUMN presentation_page.page_order IS 'Order of the page in the presentation. It means the page number';
COMMENT ON COLUMN presentation_page.model IS 'Model of the presentation, among a hard-coded list';
COMMENT ON COLUMN presentation_page.background_image IS 'Image media URL to be used as a page background';
COMMENT ON COLUMN presentation_page.background_color IS 'Background color of the page';
COMMENT ON COLUMN presentation_page.map_extent IS 'Map extent to zoom to for this page. Always stored in EPSG:4326';
COMMENT ON COLUMN presentation_page.tree_state IS 'An JSON array containing objects with key: name of the preset, and a JSON with the Lizmap layer tree state. Maximum 5 presets are stored';
COMMENT ON COLUMN presentation_page.illustration_type IS 'Type of the page illustration : media, iframe, popup, etc.';
COMMENT ON COLUMN presentation_page.illustration_media IS 'Relative path of the illustration media, such as media/some_directory/a_file.jpg';
COMMENT ON COLUMN presentation_page.illustration_url IS 'URL of the iframe';
COMMENT ON COLUMN presentation_page.illustration_feature IS 'If the illustration if a specific vector feature popup, this JSON field stores the feature layer and ID and the options (zoom to, filter, etc.)';
