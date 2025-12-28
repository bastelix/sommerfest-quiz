export type BlockType = 'hero' | 'feature_list' | 'process_steps' | 'testimonial' | 'rich_text';

export type HeroVariant = 'centered_cta' | 'media_right' | 'media_left';
export type FeatureListVariant = 'stacked_cards' | 'icon_grid';
export type ProcessStepsVariant = 'timeline_horizontal' | 'timeline_vertical';
export type TestimonialVariant = 'single_quote' | 'quote_wall';
export type RichTextVariant = 'prose';

export type BlockVariant =
  | HeroVariant
  | FeatureListVariant
  | ProcessStepsVariant
  | TestimonialVariant
  | RichTextVariant;

export interface Tokens {
  background?: 'default' | 'muted' | 'primary';
  spacing?: 'small' | 'normal' | 'large';
  width?: 'narrow' | 'normal' | 'wide';
  columns?: 'single' | 'two' | 'three' | 'four';
  accent?: 'brandA' | 'brandB' | 'brandC';
}

export interface Media {
  imageId?: string;
  alt?: string;
  focalPoint?: { x: number; y: number };
}

export interface CallToAction {
  label: string;
  href: string;
  ariaLabel?: string;
}

export interface HeroBlockData {
  eyebrow?: string;
  headline: string;
  subheadline?: string;
  media?: Media;
  cta: CallToAction;
}

export interface FeatureItem {
  id: string;
  icon?: string;
  title: string;
  description: string;
  media?: Media;
}

export interface FeatureListBlockData {
  title: string;
  intro?: string;
  items: FeatureItem[];
}

export interface ProcessStep {
  id: string;
  title: string;
  description: string;
  duration?: string;
  media?: Media;
}

export interface ProcessStepsBlockData {
  title: string;
  summary?: string;
  steps: ProcessStep[];
}

export interface Author {
  name: string;
  role?: string;
  avatarId?: string;
}

export interface TestimonialBlockData {
  quote: string;
  author: Author;
  source?: string;
}

export interface RichTextBlockData {
  body: string;
  alignment?: 'start' | 'center' | 'end' | 'justify';
}

export interface BaseBlock<TType extends BlockType, TVariant extends BlockVariant, TData> {
  id: string;
  type: TType;
  variant: TVariant;
  data: TData;
  tokens?: Tokens;
}

export type HeroBlock = BaseBlock<'hero', HeroVariant, HeroBlockData>;
export type FeatureListBlock = BaseBlock<'feature_list', FeatureListVariant, FeatureListBlockData>;
export type ProcessStepsBlock = BaseBlock<'process_steps', ProcessStepsVariant, ProcessStepsBlockData>;
export type TestimonialBlock = BaseBlock<'testimonial', TestimonialVariant, TestimonialBlockData>;
export type RichTextBlock = BaseBlock<'rich_text', RichTextVariant, RichTextBlockData>;

export type BlockContract =
  | HeroBlock
  | FeatureListBlock
  | ProcessStepsBlock
  | TestimonialBlock
  | RichTextBlock;
