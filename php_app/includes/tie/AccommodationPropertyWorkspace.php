<?php
/**
 * Property portfolio boundary for Accommodation v2.
 *
 * Properties are the root inventory entity.  This class deliberately keeps
 * their marketplace listing as a projection: editing a private property never
 * makes it searchable, bookable or active.
 */
final class UthengaAccommodationPropertyWorkspace
{
    private const PROPERTY_TYPES = ['HOTEL','LODGE','GUESTHOUSE','HOSTEL','SERVICED_APARTMENT'];
    private const AMENITIES = ['WIFI','AIR_CONDITIONING','TV','HOT_WATER','ROOM_SERVICE','WORKSPACE','MINI_FRIDGE','SAFE','PARKING','RESTAURANT','BAR','SWIMMING_POOL','CONFERENCE_ROOM','GYM','SPA','LAUNDRY','AIRPORT_TRANSFER','RECEPTION_24_7','GARDEN'];
    private const HIGHLIGHTS = ['LAKE_VIEW','FAMILY_FRIENDLY','CONFERENCE_FACILITIES','RESTAURANT','WIFI','PARKING','POOL','CITY_CENTRE','AIRPORT_TRANSFER','QUIET_LOCATION'];

    public function __construct(private PDO $db) {}

    public function workspace(string $actorId): array
    {
        $rows = $this->properties($actorId);
        $ownerIds = [];
        foreach ($rows as &$property) {
            $property['metrics'] = $this->metrics((string) $property['id']);
            $property['readiness'] = $this->readiness($property);
            if ((string) $property['vendor_id'] === $actorId) $ownerIds[] = (string) $property['id'];
        }
        unset($property);
        $active = $this->activeProperty($actorId, $ownerIds);
        $summary = ['properties' => count($rows), 'published' => 0, 'drafts' => 0, 'needs_action' => 0];
        foreach ($rows as $property) {
            $status = (string) $property['status'];
            if (in_array($status, ['PUBLISHED','ACTIVE'], true)) $summary['published']++;
            if (in_array($status, ['PRIVATE_DRAFT','SETUP_INCOMPLETE'], true)) $summary['drafts']++;
            if (!in_array($status, ['ACTIVE','PUBLISHED','ARCHIVED'], true) || !$property['readiness']['ready_for_review']) $summary['needs_action']++;
        }
        return [
            'schema_version' => 'tie-accommodation-properties/v1',
            'active_property_id' => $active,
            'summary' => $summary,
            'canonical_values' => ['property_types' => self::PROPERTY_TYPES, 'amenities' => self::AMENITIES, 'highlights' => self::HIGHLIGHTS],
            'properties' => $rows,
        ];
    }

    public function detail(string $propertyId, string $actorId): array
    {
        $property = $this->normalise($this->access($propertyId, $actorId, 'property.read'));
        $property['metrics'] = $this->metrics($propertyId);
        $property['readiness'] = $this->readiness($property);
        $property['media'] = $this->media($propertyId);
        $property['documents'] = $this->documents($propertyId);
        return ['schema_version' => 'tie-accommodation-property-detail/v1', 'property' => $property];
    }

    public function assertReadyForReview(string $propertyId, string $actorId): void
    {
        $property = $this->access($propertyId, $actorId, 'property.write');
        $normalised = $this->normalise($property);
        $readiness = $this->readiness($normalised);
        if (!$readiness['ready_for_review']) {
            $missing = array_map(fn(array $check): string => $check['label'], array_filter($readiness['checks'], fn(array $check): bool => !$check['complete']));
            throw UthengaTieErrors::validation(['setup' => 'Complete the following before review: ' . implode(', ', $missing) . '.']);
        }
    }

    public function activate(string $propertyId, string $actorId, string $correlationId): array
    {
        $property = $this->access($propertyId, $actorId, 'property.read', true);
        if ((string) $property['vendor_id'] !== $actorId) throw UthengaTieErrors::authorization();
        if ((string) $property['status'] === 'ARCHIVED') throw UthengaTieErrors::validation(['property' => 'An archived property cannot become the active management context.']);
        $this->db->prepare('INSERT INTO tie_accommodation_vendor_context (vendor_id,active_property_id) VALUES (?,?) ON DUPLICATE KEY UPDATE active_property_id=VALUES(active_property_id),updated_at=UTC_TIMESTAMP()')->execute([$actorId,$propertyId]);
        $this->audit($propertyId,$actorId,'property.activated_context','property',$propertyId,$correlationId,null,['active_property_id'=>$propertyId]);
        return ['active_property_id' => $propertyId, 'property' => $this->normalise($property)];
    }

    /** Save one wizard step.  Every field is validated and versioned server-side. */
    public function saveProfile(string $propertyId, string $actorId, array $input, string $correlationId): array
    {
        $property = $this->access($propertyId,$actorId,'property.write',true);
        $profile = $this->profile($propertyId, true);
        $expected = UthengaAccommodationContracts::integer($input['profile_version'] ?? $input['version'] ?? 0, 1, PHP_INT_MAX, 'profile_version');
        if ((int) $profile['version'] !== $expected) throw UthengaTieErrors::validation(['version' => 'This property changed in another session. Refresh before saving.']);
        $type = strtoupper(UthengaAccommodationContracts::text($input['property_type'] ?? $property['property_type'],30,true));
        if (!in_array($type,self::PROPERTY_TYPES,true)) throw UthengaTieErrors::validation(['property_type' => 'Choose a supported property type.']);
        $name = UthengaAccommodationContracts::text($input['name'] ?? $property['name'],180,true);
        // A private setup draft may be saved before its map location is known.
        // Publication readiness, not draft autosave, requires an address and coordinates.
        $address = UthengaAccommodationContracts::text($input['address'] ?? $property['address'],255);
        $city = UthengaAccommodationContracts::text($input['city'] ?? ($property['city'] ?? ''),120);
        $latitude = $this->coordinate($input['latitude'] ?? $profile['latitude'] ?? null, 'latitude', -90, 90);
        $longitude = $this->coordinate($input['longitude'] ?? $profile['longitude'] ?? null, 'longitude', -180, 180);
        if (($latitude === null) !== ($longitude === null)) throw UthengaTieErrors::validation(['location' => 'Provide both latitude and longitude, or neither.']);
        $locationSource = strtoupper(UthengaAccommodationContracts::text($input['location_source'] ?? ($profile['location_source'] ?? 'MANUAL'),20,true));
        if (!in_array($locationSource,['MANUAL','MAP_PIN','GEOCODED','DEVICE'],true)) throw UthengaTieErrors::validation(['location_source' => 'Choose a supported location source.']);
        $accuracy = $this->optionalDecimal($input['location_accuracy_m'] ?? $profile['location_accuracy_m'] ?? null, 0, 500000, 'location_accuracy_m');
        $classification = strtoupper(UthengaAccommodationContracts::text($input['quality_classification'] ?? ($profile['quality_classification'] ?? 'UNRATED'),20,true));
        if (!in_array($classification,['UNRATED','ONE','TWO','THREE','FOUR','FIVE'],true)) throw UthengaTieErrors::validation(['quality_classification' => 'Choose a supported self-declared classification.']);
        $profileAmenities = is_array($profile['amenities'] ?? null) ? $profile['amenities'] : (json_decode((string) ($profile['amenities'] ?? '[]'), true) ?: []);
        $profileHighlights = is_array($profile['highlights'] ?? null) ? $profile['highlights'] : (json_decode((string) ($profile['highlights'] ?? '[]'), true) ?: []);
        $profilePolicy = is_array($profile['guest_policy'] ?? null) ? $profile['guest_policy'] : (json_decode((string) ($profile['guest_policy'] ?? '{}'), true) ?: []);
        $amenities = $this->canonicalList($input['amenities'] ?? $profileAmenities, self::AMENITIES, 'amenities');
        $highlights = $this->canonicalList($input['highlights'] ?? $profileHighlights, self::HIGHLIGHTS, 'highlights');
        $guestPolicy = $this->guestPolicy($input['guest_policy'] ?? $profilePolicy);
        $now = ($latitude !== null && $longitude !== null) ? gmdate('Y-m-d H:i:s') : null;
        $this->db->beginTransaction();
        try {
            $before = array_merge($property, ['profile' => $profile]);
            $this->db->prepare('UPDATE tie_accommodation_properties SET name=?,property_type=?,description=?,address=?,city=?,phone=?,email=?,check_in_time=?,check_out_time=?,version=version+1 WHERE id=? AND version=?')
                ->execute([$name,$type,UthengaAccommodationContracts::text($input['description'] ?? $property['description'],5000),$address,$city ?: null,UthengaAccommodationContracts::text($input['phone'] ?? ($property['phone'] ?? ''),60) ?: null,UthengaAccommodationContracts::text($input['email'] ?? ($property['email'] ?? ''),190) ?: null,$this->time($input['check_in_time'] ?? $property['check_in_time']),$this->time($input['check_out_time'] ?? $property['check_out_time']),$propertyId,(int)$property['property_version']]);
            $this->db->prepare('UPDATE tie_accommodation_property_profiles SET display_name=?,short_description=?,region=?,district=?,locality=?,latitude=?,longitude=?,location_source=?,location_accuracy_m=?,location_captured_at=COALESCE(?,location_captured_at),quality_classification=?,legal_business_name=?,trading_name=?,business_registration=?,tax_identifier=?,website_url=?,highlights=?,amenities=?,guest_policy=?,version=version+1 WHERE property_id=? AND version=?')
                ->execute([
                    UthengaAccommodationContracts::text($input['display_name'] ?? ($profile['display_name'] ?? ''),180) ?: null,
                    UthengaAccommodationContracts::text($input['short_description'] ?? ($profile['short_description'] ?? ''),500) ?: null,
                    UthengaAccommodationContracts::text($input['region'] ?? ($profile['region'] ?? ''),120) ?: null,
                    UthengaAccommodationContracts::text($input['district'] ?? ($profile['district'] ?? ''),120) ?: null,
                    UthengaAccommodationContracts::text($input['locality'] ?? ($profile['locality'] ?? ''),120) ?: null,
                    $latitude,$longitude,$locationSource,$accuracy,$now,$classification,
                    UthengaAccommodationContracts::text($input['legal_business_name'] ?? ($profile['legal_business_name'] ?? ''),190) ?: null,
                    UthengaAccommodationContracts::text($input['trading_name'] ?? ($profile['trading_name'] ?? ''),190) ?: null,
                    UthengaAccommodationContracts::text($input['business_registration'] ?? ($profile['business_registration'] ?? ''),120) ?: null,
                    UthengaAccommodationContracts::text($input['tax_identifier'] ?? ($profile['tax_identifier'] ?? ''),120) ?: null,
                    $this->url($input['website_url'] ?? ($profile['website_url'] ?? null)),json_encode($highlights),json_encode($amenities),json_encode($guestPolicy),$propertyId,$expected
                ]);
            $after = $this->property($propertyId,$actorId);
            $this->projectListing($after);
            $this->audit($propertyId,$actorId,'property.profile_saved','property',$propertyId,$correlationId,$before,$after);
            $this->db->commit();
            return $after;
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function duplicate(string $propertyId, string $actorId, string $correlationId): array
    {
        $source = $this->access($propertyId,$actorId,'property.write',true);
        if ((string)$source['vendor_id'] !== $actorId) throw UthengaTieErrors::authorization();
        $sourceProfile = $this->profile($propertyId);
        $newId = $this->uuid(); $profileId = $this->uuid(); $listingId = 'ACC-'.strtoupper(bin2hex(random_bytes(6)));
        $owner = $this->db->prepare('SELECT name,email FROM users WHERE id=? LIMIT 1');$owner->execute([$actorId]);$ownerRow=$owner->fetch() ?: ['name'=>'Uthenga vendor','email'=>''];
        $this->db->beginTransaction();
        try {
            $name = mb_substr((string)$source['name'].' (Copy)',0,180);
            $this->db->prepare('INSERT INTO tie_vendor_service_profiles (id,vendor_id,profile_type,profile_name,status,is_active,listing_id,configuration) VALUES (?,?,"accommodation",?,"PRIVATE_DRAFT",0,?,?)')->execute([$profileId,$actorId,$name,$listingId,json_encode(['setup_complete'=>false,'property_id'=>$newId,'copied_from'=>$propertyId])]);
            $this->db->prepare('INSERT INTO listings (id,listing_type,title,description,location,image,gallery,vendor_id,vendor_name,rating,featured,is_active,meta) VALUES (?,"accommodation",?,?,?,?,?,?,?,0,0,0,?)')->execute([$listingId,$name,(string)$source['description'],(string)$source['address'],(string)($source['image_url']??''),json_encode([]),$actorId,$ownerRow['name'],json_encode(['propertyId'=>$newId,'privateDraft'=>true])]);
            $this->db->prepare('INSERT INTO tie_accommodation_properties (id,vendor_id,service_profile_id,listing_id,name,property_type,description,address,city,country_code,timezone,currency,phone,email,image_url,check_in_time,check_out_time,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"PRIVATE_DRAFT")')->execute([$newId,$actorId,$profileId,$listingId,$name,$source['property_type'],$source['description'],$source['address'],$source['city'],$source['country_code'],$source['timezone'],$source['currency'],$source['phone'],$source['email'],$source['image_url'],$source['check_in_time'],$source['check_out_time']]);
            $this->db->prepare('INSERT INTO tie_accommodation_property_profiles (property_id,display_name,short_description,region,district,locality,quality_classification,legal_business_name,trading_name,business_registration,tax_identifier,website_url,highlights,amenities,guest_policy,verification_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"NOT_SUBMITTED")')->execute([$newId,$sourceProfile['display_name'],$sourceProfile['short_description'],$sourceProfile['region'],$sourceProfile['district'],$sourceProfile['locality'],$sourceProfile['quality_classification'],$sourceProfile['legal_business_name'],$sourceProfile['trading_name'],$sourceProfile['business_registration'],$sourceProfile['tax_identifier'],$sourceProfile['website_url'],$sourceProfile['highlights'],$sourceProfile['amenities'],$sourceProfile['guest_policy']]);
            $this->db->prepare('INSERT INTO tie_accommodation_cancellation_policies (id,property_id,name,free_cancel_hours,penalty_percent,no_show_percent,is_active) SELECT UUID(),?,name,free_cancel_hours,penalty_percent,no_show_percent,is_active FROM tie_accommodation_cancellation_policies WHERE property_id=?')->execute([$newId,$propertyId]);
            $this->db->prepare('INSERT INTO tie_accommodation_staff_memberships (id,property_id,user_id,invited_email,role_key,status,invited_by,accepted_at) VALUES (?,?,?,? ,"OWNER","ACTIVE",?,UTC_TIMESTAMP())')->execute([$this->uuid(),$newId,$actorId,(string)$ownerRow['email'],$actorId]);
            $created = $this->property($newId,$actorId);
            $this->audit($newId,$actorId,'property.duplicated','property',$newId,$correlationId,['source_property_id'=>$propertyId],$created);
            $this->db->commit(); return $created;
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function listMedia(string $propertyId,string $actorId): array { $this->access($propertyId,$actorId,'property.read'); return ['media'=>$this->media($propertyId),'documents'=>$this->documents($propertyId)]; }

    public function uploadMedia(string $propertyId,string $actorId,array $input,array $file,string $correlationId): array
    {
        $property=$this->access($propertyId,$actorId,'property.write',true);
        $stored=$this->storeUpload($propertyId,$file,['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'],10*1024*1024,'accommodation-media');
        $category=strtoupper(UthengaAccommodationContracts::text($input['media_category']??'OTHER',20,true));$categories=['EXTERIOR','INTERIOR','ROOMS','BATHROOM','DINING','FACILITIES','POOL','CONFERENCE','LANDSCAPE','OTHER'];if(!in_array($category,$categories,true))throw UthengaTieErrors::validation(['media_category'=>'Choose a supported media category.']);
        $cover=!empty($input['is_cover']);$id=$this->uuid();
        $sequence=$this->db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM tie_accommodation_property_media WHERE property_id=?');$sequence->execute([$propertyId]);$order=(int)$sequence->fetchColumn();
        $this->db->beginTransaction();try{if($cover)$this->db->prepare('UPDATE tie_accommodation_property_media SET is_cover=0,version=version+1 WHERE property_id=? AND is_cover=1')->execute([$propertyId]);$this->db->prepare('INSERT INTO tie_accommodation_property_media (id,property_id,storage_name,original_name,mime_type,size_bytes,checksum_sha256,media_category,caption,alt_text,sort_order,is_cover,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$id,$propertyId,$stored['storage_name'],$stored['original_name'],$stored['mime_type'],$stored['size_bytes'],$stored['checksum'],$category,UthengaAccommodationContracts::text($input['caption']??'',500)?:null,UthengaAccommodationContracts::text($input['alt_text']??'',255)?:null,$order,$cover?1:0,$actorId]);$row=$this->mediaRow($id);if($cover){$url=$this->mediaUrl($id);$this->db->prepare('UPDATE tie_accommodation_properties SET image_url=?,version=version+1 WHERE id=?')->execute([$url,$propertyId]);$this->projectListing($this->property($propertyId,$actorId));}$this->audit($propertyId,$actorId,'property.media_uploaded','property_media',$id,$correlationId,null,$row);$this->db->commit();return $this->publicMedia($row);}catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();@unlink($stored['path']);throw $error;}
    }

    public function updateMedia(string $propertyId,string $actorId,array $input,string $correlationId): array
    {
        $this->access($propertyId,$actorId,'property.write',true);$id=UthengaAccommodationContracts::id($input['media_id']??'','media_id');$before=$this->mediaRow($id,$propertyId,true);$version=UthengaAccommodationContracts::integer($input['version']??0,1,PHP_INT_MAX,'version');if((int)$before['version']!==$version)throw UthengaTieErrors::validation(['version'=>'Media changed in another session. Refresh before saving.']);$cover=!empty($input['is_cover']);$this->db->beginTransaction();try{if($cover)$this->db->prepare('UPDATE tie_accommodation_property_media SET is_cover=0,version=version+1 WHERE property_id=? AND is_cover=1 AND id<>?')->execute([$propertyId,$id]);$this->db->prepare('UPDATE tie_accommodation_property_media SET media_category=?,caption=?,alt_text=?,sort_order=?,is_cover=?,version=version+1 WHERE id=? AND version=?')->execute([strtoupper(UthengaAccommodationContracts::text($input['media_category']??$before['media_category'],20,true)),UthengaAccommodationContracts::text($input['caption']??($before['caption']??''),500)?:null,UthengaAccommodationContracts::text($input['alt_text']??($before['alt_text']??''),255)?:null,UthengaAccommodationContracts::integer($input['sort_order']??$before['sort_order'],0,100000,'sort_order'),$cover?1:0,$id,$version]);$after=$this->mediaRow($id);if($cover){$this->db->prepare('UPDATE tie_accommodation_properties SET image_url=?,version=version+1 WHERE id=?')->execute([$this->mediaUrl($id),$propertyId]);$this->projectListing($this->property($propertyId,$actorId));}$this->audit($propertyId,$actorId,'property.media_updated','property_media',$id,$correlationId,$before,$after);$this->db->commit();return $this->publicMedia($after);}catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
    }

    public function removeMedia(string $propertyId,string $actorId,array $input,string $correlationId): void
    {
        $this->access($propertyId,$actorId,'property.write',true);$id=UthengaAccommodationContracts::id($input['media_id']??'','media_id');$row=$this->mediaRow($id,$propertyId,true);$this->db->prepare('DELETE FROM tie_accommodation_property_media WHERE id=? AND property_id=?')->execute([$id,$propertyId]);$this->audit($propertyId,$actorId,'property.media_removed','property_media',$id,$correlationId,$row,null);$path=$this->storagePath('accommodation-media',(string)$row['storage_name']);if(is_file($path))@unlink($path);
    }

    public function uploadDocument(string $propertyId,string $actorId,array $input,array $file,string $correlationId): array
    {
        $this->access($propertyId,$actorId,'property.write',true);$stored=$this->storeUpload($propertyId,$file,['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'],10*1024*1024,'accommodation-documents');$category=strtoupper(UthengaAccommodationContracts::text($input['category']??'OTHER',20,true));if(!in_array($category,['LICENSE','INSURANCE','SAFETY','TAX','POLICY','OTHER'],true))throw UthengaTieErrors::validation(['category'=>'Choose a supported document category.']);$id=$this->uuid();$expires=$this->dateOrNull($input['expires_on']??null,'expires_on');$this->db->prepare('INSERT INTO tie_accommodation_documents (id,property_id,category,original_name,storage_name,mime_type,size_bytes,checksum_sha256,expires_on,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$id,$propertyId,$category,$stored['original_name'],$stored['storage_name'],$stored['mime_type'],$stored['size_bytes'],$stored['checksum'],$expires,$actorId]);$row=$this->documentRow($id);$this->audit($propertyId,$actorId,'property.document_uploaded','property_document',$id,$correlationId,null,$row);return $this->publicDocument($row);
    }

    public function document(string $documentId, ?string $actorId): array
    {
        $row=$this->documentRow($documentId);if($actorId===null)$this->deny();$this->access((string)$row['property_id'],$actorId,'property.read');return $row;
    }

    public function mediaForDelivery(string $mediaId, ?string $actorId): array
    {
        $row=$this->mediaRow($mediaId);if(!in_array((string)$row['property_status'],['PUBLISHED','ACTIVE'],true)){if($actorId===null)$this->deny();$this->access((string)$row['property_id'],$actorId,'property.read');}return $row;
    }

    private function properties(string $actorId): array
    {
        $sql="SELECT p.*,pf.*,p.version property_version,pf.version profile_version, CASE WHEN p.vendor_id=? THEN 'OWNER' ELSE m.role_key END access_role, (SELECT l.rating FROM listings l WHERE l.id=p.listing_id) listing_rating
              FROM tie_accommodation_properties p
              LEFT JOIN tie_accommodation_staff_memberships m ON m.property_id=p.id AND m.user_id=? AND m.status='ACTIVE'
              LEFT JOIN tie_accommodation_property_profiles pf ON pf.property_id=p.id
              WHERE p.vendor_id=? OR m.user_id=? ORDER BY p.updated_at DESC";
        $q=$this->db->prepare($sql);$q->execute([$actorId,$actorId,$actorId,$actorId]);return array_map(fn(array $row)=>$this->normalise($row),$q->fetchAll());
    }
    private function property(string $propertyId,string $actorId): array { return $this->normalise($this->access($propertyId,$actorId,'property.read')); }
    private function access(string $propertyId,string $actorId,string $permission,bool $lock=false): array
    {
        $sql="SELECT p.*,pf.*,p.version property_version,pf.version profile_version,CASE WHEN p.vendor_id=? THEN 'OWNER' ELSE m.role_key END access_role,(SELECT l.rating FROM listings l WHERE l.id=p.listing_id) listing_rating FROM tie_accommodation_properties p LEFT JOIN tie_accommodation_staff_memberships m ON m.property_id=p.id AND m.user_id=? AND m.status='ACTIVE' LEFT JOIN tie_accommodation_property_profiles pf ON pf.property_id=p.id WHERE p.id=? AND (p.vendor_id=? OR m.user_id=?) LIMIT 1".($lock?' FOR UPDATE':'');
        $q=$this->db->prepare($sql);$q->execute([$actorId,$actorId,$propertyId,$actorId,$actorId]);$row=$q->fetch();if(!is_array($row)||!UthengaAccommodationPermissions::allows((string)$row['access_role'],$permission))$this->deny();return $row;
    }
    private function profile(string $propertyId,bool $lock=false): array {$q=$this->db->prepare('SELECT * FROM tie_accommodation_property_profiles WHERE property_id=? LIMIT 1'.($lock?' FOR UPDATE':''));$q->execute([$propertyId]);$row=$q->fetch();if(is_array($row))return $row;$this->db->prepare('INSERT IGNORE INTO tie_accommodation_property_profiles (property_id,location_source,quality_classification,highlights,amenities,guest_policy) VALUES (?,"MANUAL","UNRATED",JSON_ARRAY(),JSON_ARRAY(),JSON_OBJECT("children_allowed",false,"pets_allowed",false,"smoking_allowed",false,"events_allowed",false,"visitors_allowed",false,"quiet_hours_from","22:00","quiet_hours_to","06:00"))')->execute([$propertyId]);$q->execute([$propertyId]);$row=$q->fetch();if(!is_array($row))throw UthengaTieErrors::providerUnavailable('accommodation_property_profile');return $row;}
    private function readiness(array $property): array
    {
        $id=(string)$property['id'];$media=$this->db->prepare('SELECT COUNT(*) FROM tie_accommodation_property_media WHERE property_id=?');$media->execute([$id]);$documents=$this->db->prepare("SELECT COUNT(*) FROM tie_accommodation_documents WHERE property_id=? AND status='ACTIVE'");$documents->execute([$id]);$rooms=$this->db->prepare('SELECT COUNT(*) FROM room_types WHERE property_id=? AND is_active=1');$rooms->execute([$id]);$rates=$this->db->prepare('SELECT COUNT(*) FROM tie_accommodation_rate_plans WHERE property_id=? AND is_active=1');$rates->execute([$id]);$policies=$this->db->prepare('SELECT COUNT(*) FROM tie_accommodation_cancellation_policies WHERE property_id=? AND is_active=1');$policies->execute([$id]);$checks=[
            ['key'=>'identity','label'=>'Identity','complete'=>trim((string)$property['name'])!==''],
            ['key'=>'location','label'=>'Location','complete'=>trim((string)$property['address'])!=='' && $property['latitude']!==null && $property['longitude']!==null],
            ['key'=>'description','label'=>'Description','complete'=>trim((string)($property['description']??''))!=='' && trim((string)($property['short_description']??''))!==''],
            ['key'=>'media','label'=>'Cover media','complete'=>(int)$media->fetchColumn()>0 || trim((string)($property['image_url']??''))!==''],
            ['key'=>'amenities','label'=>'Amenities','complete'=>count($property['amenities'])>0],
            ['key'=>'policies','label'=>'Policies','complete'=>(int)$policies->fetchColumn()>0],
            ['key'=>'business','label'=>'Business information','complete'=>trim((string)($property['legal_business_name']??''))!=='' && trim((string)($property['business_registration']??''))!==''],
            ['key'=>'documents','label'=>'Verification documents','complete'=>(int)$documents->fetchColumn()>0],
            ['key'=>'rooms','label'=>'Room inventory','complete'=>(int)$rooms->fetchColumn()>0],
            ['key'=>'rates','label'=>'Room pricing','complete'=>(int)$rates->fetchColumn()>0],
        ];$completed=count(array_filter($checks,fn($check)=>$check['complete']));$total=count($checks);return ['checks'=>$checks,'completed'=>$completed,'total'=>$total,'percent'=>(int)round(($completed/max(1,$total))*100),'ready_for_review'=>$completed===$total];
    }
    private function metrics(string $propertyId): array
    {
        $q=$this->db->prepare("SELECT (SELECT COUNT(*) FROM room_types WHERE property_id=? AND is_active=1) room_types,(SELECT COALESCE(SUM(total_rooms),0) FROM room_types WHERE property_id=? AND is_active=1) rooms,(SELECT COUNT(*) FROM tie_accommodation_reservations WHERE property_id=?) reservations,(SELECT COALESCE(SUM(amount_paid),0) FROM tie_accommodation_reservations WHERE property_id=? AND status NOT IN ('CANCELLED','EXPIRED','NO_SHOW')) recorded_paid,(SELECT COUNT(*) FROM tie_accommodation_inventory_nights WHERE property_id=? AND stay_date=CURDATE()) nightly_rows");$q->execute([$propertyId,$propertyId,$propertyId,$propertyId,$propertyId]);$row=$q->fetch() ?: [];return ['room_types'=>(int)($row['room_types']??0),'rooms'=>(int)($row['rooms']??0),'reservations'=>(int)($row['reservations']??0),'recorded_paid'=>(float)($row['recorded_paid']??0),'nightly_rows'=>(int)($row['nightly_rows']??0)];
    }
    private function activeProperty(string $vendorId,array $owned): ?string {$q=$this->db->prepare('SELECT active_property_id FROM tie_accommodation_vendor_context WHERE vendor_id=?');$q->execute([$vendorId]);$id=$q->fetchColumn();if(is_string($id)&&in_array($id,$owned,true))return $id;return $owned[0]??null;}
    private function media(string $propertyId): array {$q=$this->db->prepare('SELECT m.*,p.status property_status FROM tie_accommodation_property_media m INNER JOIN tie_accommodation_properties p ON p.id=m.property_id WHERE m.property_id=? ORDER BY m.is_cover DESC,m.sort_order,m.created_at');$q->execute([$propertyId]);return array_map([$this,'publicMedia'],$q->fetchAll());}
    private function documents(string $propertyId): array {$q=$this->db->prepare('SELECT * FROM tie_accommodation_documents WHERE property_id=? ORDER BY created_at DESC');$q->execute([$propertyId]);return array_map([$this,'publicDocument'],$q->fetchAll());}
    private function mediaRow(string $id,?string $propertyId=null,bool $lock=false): array {$sql='SELECT m.*,p.status property_status FROM tie_accommodation_property_media m INNER JOIN tie_accommodation_properties p ON p.id=m.property_id WHERE m.id=?'.($propertyId?' AND m.property_id=?':'').' LIMIT 1'.($lock?' FOR UPDATE':'');$q=$this->db->prepare($sql);$q->execute($propertyId?[$id,$propertyId]:[$id]);$row=$q->fetch();if(!is_array($row))throw UthengaTieErrors::authorization();return $row;}
    private function documentRow(string $id): array {$q=$this->db->prepare('SELECT * FROM tie_accommodation_documents WHERE id=? LIMIT 1');$q->execute([$id]);$row=$q->fetch();if(!is_array($row))throw UthengaTieErrors::authorization();return $row;}
    private function publicMedia(array $row): array {return ['id'=>(string)$row['id'],'url'=>$this->mediaUrl((string)$row['id']),'category'=>(string)$row['media_category'],'caption'=>$row['caption']?:null,'alt_text'=>$row['alt_text']?:null,'sort_order'=>(int)$row['sort_order'],'is_cover'=>(bool)$row['is_cover'],'version'=>(int)$row['version'],'created_at'=>(string)$row['created_at']];}
    private function publicDocument(array $row): array {return ['id'=>(string)$row['id'],'category'=>(string)$row['category'],'original_name'=>(string)$row['original_name'],'mime_type'=>(string)$row['mime_type'],'size_bytes'=>(int)$row['size_bytes'],'expires_on'=>$row['expires_on']?:null,'status'=>(string)$row['status'],'version'=>(int)$row['version'],'created_at'=>(string)$row['created_at']];}
    private function normalise(array $row): array {foreach(['highlights','amenities','guest_policy'] as $json)$row[$json]=is_array($row[$json]??null)?$row[$json]:(json_decode((string)($row[$json]??'[]'),true)?:[]);$row['property_version']=(int)($row['property_version']??$row['version']??1);$row['profile_version']=(int)($row['profile_version']??1);$row['version']=$row['property_version'];$row['access_capabilities']=UthengaAccommodationPermissions::capabilities((string)($row['access_role']??'OWNER'));return $row;}
    private function projectListing(array $property): void
    {
        if(empty($property['listing_id']))return;$location=implode(', ',array_filter([(string)($property['address']??''),(string)($property['locality']??''),(string)($property['city']??'')]));$meta=['propertyId'=>$property['id'],'city'=>$property['city']??null,'coordinates'=>($property['latitude']!==null&&$property['longitude']!==null)?['lat'=>(float)$property['latitude'],'lng'=>(float)$property['longitude'],'source'=>$property['location_source']]:null,'highlights'=>$property['highlights'],'amenities'=>$property['amenities'],'propertyType'=>$property['property_type'],'privateDraft'=>!in_array((string)$property['status'],['PUBLISHED','ACTIVE'],true)];$q=$this->db->prepare('SELECT meta FROM listings WHERE id=? AND vendor_id=? LIMIT 1 FOR UPDATE');$q->execute([$property['listing_id'],$property['vendor_id']]);$existing=$q->fetchColumn();$merged=array_merge(json_decode((string)$existing,true)?:[],$meta);$this->db->prepare('UPDATE listings SET title=?,description=?,location=?,image=?,meta=? WHERE id=? AND vendor_id=?')->execute([$property['display_name']?:$property['name'],$property['description'],$location,$property['image_url']??'',json_encode($merged,JSON_UNESCAPED_SLASHES),$property['listing_id'],$property['vendor_id']]);
    }
    private function storeUpload(string $propertyId,array $file,array $allowed,int $limit,string $bucket): array
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||empty($file['tmp_name'])||!is_uploaded_file((string)$file['tmp_name']))throw UthengaTieErrors::validation(['file'=>'Choose a valid uploaded file.']);$size=(int)($file['size']??0);if($size<1||$size>$limit)throw UthengaTieErrors::validation(['file'=>'The file must be smaller than '.($limit/1024/1024).' MB.']);$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file((string)$file['tmp_name']);if(!isset($allowed[$mime]))throw UthengaTieErrors::validation(['file'=>'That file type is not permitted.']);if(str_starts_with($mime,'image/')&&@getimagesize((string)$file['tmp_name'])===false)throw UthengaTieErrors::validation(['file'=>'The uploaded image is invalid.']);        $folder=rtrim(__DIR__.'/../../storage/'.$bucket,'/');if(!is_dir($folder)&&!mkdir($folder,0775,true)&&!is_dir($folder))throw UthengaTieErrors::providerUnavailable('secure_file_storage');@chmod($folder,0775);$name=$propertyId.'-'.bin2hex(random_bytes(18)).'.'.$allowed[$mime];$path=$folder.'/'.$name;if(!move_uploaded_file((string)$file['tmp_name'],$path))throw UthengaTieErrors::providerUnavailable('secure_file_storage');@chmod($path,0600);return ['storage_name'=>$name,'original_name'=>mb_substr(basename((string)($file['name']??'upload')),0,255),'mime_type'=>$mime,'size_bytes'=>$size,'checksum'=>hash_file('sha256',$path),'path'=>$path];
    }
    private function storagePath(string $bucket,string $name): string {return rtrim(__DIR__.'/../../storage/'.$bucket,'/').'/'.basename($name);}
    private function mediaUrl(string $id): string {return rtrim(BASE_URL,'/').'/api/tie/accommodation/media.php?id='.rawurlencode($id);}
    private function canonicalList($value,array $allowed,string $field): array {$items=is_array($value)?$value:[];$out=[];foreach($items as $item){$item=strtoupper(trim((string)$item));if(!in_array($item,$allowed,true))throw UthengaTieErrors::validation([$field=>'Choose only canonical '.$field.'.']);$out[$item]=$item;}return array_values($out);}
    private function guestPolicy($value): array {$value=is_array($value)?$value:[];return ['children_allowed'=>!empty($value['children_allowed']),'pets_allowed'=>!empty($value['pets_allowed']),'smoking_allowed'=>!empty($value['smoking_allowed']),'events_allowed'=>!empty($value['events_allowed']),'visitors_allowed'=>!empty($value['visitors_allowed']),'quiet_hours_from'=>$this->time($value['quiet_hours_from']??'22:00'),'quiet_hours_to'=>$this->time($value['quiet_hours_to']??'06:00')];}
    private function coordinate($value,string $field,float $min,float $max): ?float {if($value===null||$value==='')return null;return UthengaAccommodationContracts::decimal($value,$min,$max,$field);}
    private function optionalDecimal($value,float $min,float $max,string $field): ?float {if($value===null||$value==='')return null;return UthengaAccommodationContracts::decimal($value,$min,$max,$field);}
    private function time($value): string {$value=trim((string)$value);if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',$value))throw UthengaTieErrors::validation(['time'=>'Use a valid 24-hour time.']);return strlen($value)===5?$value.':00':$value;}
    private function dateOrNull($value,string $field): ?string {if($value===null||$value==='')return null;try{return (new DateTimeImmutable((string)$value))->format('Y-m-d');}catch(Throwable){throw UthengaTieErrors::validation([$field=>'Use a valid date.']);}}
    private function url($value): ?string {$value=trim((string)$value);if($value==='')return null;if(!filter_var($value,FILTER_VALIDATE_URL)||!preg_match('~^https?://~i',$value))throw UthengaTieErrors::validation(['website_url'=>'Use a valid http or https URL.']);return mb_substr($value,0,500);}
    private function audit(string $propertyId,string $actorId,string $action,string $entityType,string $entityId,string $correlation,$before,$after): void {$this->db->prepare('INSERT INTO tie_accommodation_audit_events (property_id,actor_id,action_key,entity_type,entity_id,correlation_id,before_state,after_state) VALUES (?,?,?,?,?,?,?,?)')->execute([$propertyId,$actorId,$action,$entityType,$entityId,$correlation,$before===null?null:json_encode($this->redact($before)),$after===null?null:json_encode($this->redact($after))]);}
    private function redact($value){if(!is_array($value))return $value;foreach(['guest_email','guest_phone','tax_identifier','business_registration'] as $key)if(isset($value[$key]))$value[$key]='[redacted]';return $value;}
    private function uuid(): string {$bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&0x0f)|0x40);$bytes[8]=chr((ord($bytes[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($bytes),4));}
    private function deny(): void {throw UthengaTieErrors::authorization();}
}
