import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const previewDirectory = path.dirname( fileURLToPath( import.meta.url ) );
const contentPath = path.join( previewDirectory, 'preview-content.json' );
const mediaPath = path.join( previewDirectory, 'preview-media.json' );

const mediaManifest = JSON.parse( fs.readFileSync( mediaPath, 'utf8' ) );
const baseAssets = mediaManifest.assets.filter( ( asset ) => ! asset.id.endsWith( '-en' ) );
const photoAssetIds = baseAssets
	.map( ( asset ) => asset.id )
	.filter( ( assetId ) => assetId.startsWith( 'photo-' ) );

const englishAltText = [
	'A decorated courtyard prepared for a Buddhist celebration in Sa Dec',
	'The entrance to Thien Lam Pagoda in Vung Tau',
	'The tree-lined facade of Thanh Pagoda in Lang Son',
	'The courtyard of Dai Giac Pagoda beneath a mature tree',
	'An ancient bell in the grounds of Thien Mu Pagoda',
	'Visitors walking through a Vietnamese temple complex',
	'A quiet pagoda roof framed by trees and open sky',
	'A Buddhist statue overlooking a temple courtyard',
	'Decorative details preserved on a historic pagoda',
	'A rural temple landscape in the late afternoon',
];
const englishAssets = photoAssetIds.slice( 0, 10 ).map( ( assetId, index ) => {
	const asset = baseAssets.find( ( candidate ) => candidate.id === assetId );
	return {
		...asset,
		id: `${ asset.id }-en`,
		source_title: `Vietnam Buddhist heritage photograph ${ index + 1 }`,
		description: 'An attributed development fixture used by the English-language Vietnam Buddhism editorial surface.',
		alt: englishAltText[ index ],
	};
} );
const englishPhotoAssetIds = englishAssets.map( ( asset ) => asset.id );
mediaManifest.assets = [ ...baseAssets, ...englishAssets ];

const videoCatalog = [
	{ id: 'A20Lx3sv8hs', poster: 'video-poster-0008' },
	{ id: 'X3iS2L0Y_xw', poster: 'video-poster-0012' },
	{ id: '2ZSW6vPbEjo', poster: 'video-poster-0019' },
	{ id: 'fTcmiAD_GwQ', poster: 'video-poster-0039' },
];

const vietnameseAngles = [
	'những ghi nhận từ một ngày nhiều kết nối',
	'câu chuyện phía sau những việc làm bền bỉ',
	'khi trải nghiệm nhỏ mở ra thay đổi lớn',
	'một cách tiếp cận gần gũi và thực tế',
	'điều còn lại sau những cuộc gặp gỡ',
	'góc nhìn từ người trực tiếp tham gia',
	'những chi tiết tạo nên giá trị lâu dài',
	'từ ý tưởng ban đầu đến thực hành mỗi ngày',
	'một hành trình được kể bằng hình ảnh',
	'bài học về sự lắng nghe và sẻ chia',
	'những chuyển động mới trong đời sống hôm nay',
	'cách cộng đồng cùng gìn giữ một giá trị đẹp',
	'thêm một lựa chọn cho nhịp sống cân bằng',
	'khi truyền thống gặp nhu cầu của người trẻ',
	'một lát cắt bình dị nhưng nhiều gợi mở',
	'những kinh nghiệm có thể áp dụng ngay',
	'để tinh thần phụng sự đi vào việc cụ thể',
	'câu chuyện về trách nhiệm và lòng tin',
	'nhìn lại tiến trình bằng dữ liệu và trải nghiệm',
	'mở rộng không gian đối thoại trong cộng đồng',
];

const englishAngles = [
	'notes from a day of meaningful connection',
	'the people and practices behind lasting change',
	'how a small experience can reshape a community',
	'a practical approach rooted in everyday life',
	'what remains after the gathering ends',
	'perspectives from people taking part',
	'the details that sustain a shared tradition',
	'from an initial idea to a daily practice',
	'a visual journey through living heritage',
	'lessons in listening, care and participation',
	'new movements in contemporary Buddhist life',
	'how communities preserve a valued practice',
	'an invitation to a more balanced rhythm',
	'where tradition meets a younger generation',
	'an ordinary scene with wider implications',
	'experiences that can inform future work',
	'turning service into concrete action',
	'a story of responsibility and trust',
	'reviewing progress through evidence and experience',
	'expanding space for dialogue and belonging',
];

const emagazineStories = [
	{
		title: 'Một ngày dưới mái ngói trăm năm',
		sapo: 'Từ tiếng chổi tre trước bình minh đến hồi chuông cuối ngày, câu chuyện theo chân những người âm thầm giữ cho một ngôi chùa cổ tiếp tục sống trong nhịp làng hôm nay.',
		setting: 'Ngôi chùa nằm sau một hàng sao già, nơi dấu vết của nhiều lần tu sửa vẫn hiện trên nền gạch và những thanh xà sẫm màu.',
		opening: 'Bốn giờ ba mươi, cánh cổng gỗ mở ra trong tiếng bản lề khẽ động. Sân còn đẫm sương, nhưng gian bếp đã đỏ lửa và chiếc chổi tre bắt đầu vẽ những đường dài trên nền gạch.',
		chapters: [
			{ heading: 'Trước khi tiếng chuông ngân', focus: 'Những công việc mở đầu ngày mới cho thấy di sản được gìn giữ bằng lịch trực, sự phối hợp và hiểu biết rất cụ thể về từng khoảng sân, mái ngói và pho tượng.' },
			{ heading: 'Ngôi chùa trong ký ức của làng', focus: 'Lời kể của ba thế hệ được đặt cạnh dấu vết kiến trúc để phân biệt điều có thể quan sát, điều thuộc về ký ức và chi tiết còn cần tra cứu trong tư liệu.' },
			{ heading: 'Giữ một nơi chốn luôn mở', focus: 'Câu chuyện khép lại ở bài toán dung hòa bảo tồn với nhu cầu sinh hoạt, từ lối đi an toàn đến cách hướng dẫn khách mà không làm mất sự tĩnh lặng.' },
		],
		quote: 'Một mái chùa không chỉ được giữ bằng lần đại trùng tu, mà bằng việc mỗi sáng vẫn có người biết viên ngói nào vừa xô lệch.',
		quoteBy: 'Người trông nom di sản trong câu chuyện',
		takeaways: [ 'Lập nhật ký những thay đổi nhỏ của công trình.', 'Ghi âm ký ức khi người kể còn có thể đối chiếu.', 'Tách khu vực sinh hoạt khỏi vị trí cần bảo tồn.', 'Công khai đầu mối tiếp nhận tư liệu từ cộng đồng.' ],
	},
	{
		title: 'Bàn tay giữ hồn tượng Phật',
		sapo: 'Trong xưởng gỗ phủ bụi mịn, một pho tượng đi qua nhiều tháng đục, gọt và phủ sơn. Tuyến ảnh kể về tay nghề, sự kiên nhẫn và câu hỏi ai sẽ tiếp tục nghề mai sau.',
		setting: 'Xưởng làm tượng nép sau khu chợ nhỏ, sáng bằng cửa mái và luôn có mùi gỗ mới trộn với mùi sơn ta.',
		opening: 'Người thợ không bắt đầu bằng nhát đục. Ông đi quanh khối gỗ, áp lòng bàn tay lên những đường vân rồi đánh dấu phần lõi cần tránh trước khi vẽ nét đầu tiên.',
		chapters: [
			{ heading: 'Nhìn thấy hình hài trong thớ gỗ', focus: 'Từ chọn gỗ, dựng tỷ lệ đến tạo dáng, mỗi quyết định kỹ thuật đều gắn với hiểu biết về chất liệu và quy chuẩn tạo tượng chứ không dựa vào cảm hứng nhất thời.' },
			{ heading: 'Những lớp màu không được vội', focus: 'Công đoạn sơn, thếp và làm khô tạo nên một nhịp kể chậm; ảnh cận cảnh bàn tay và bề mặt giúp người đọc nhận ra giá trị của thời gian trong thủ công.' },
			{ heading: 'Người học nghề ở lại', focus: 'Khi đơn hàng công nghiệp rút ngắn thời gian sản xuất, lớp thợ trẻ phải tìm cách học kỹ thuật cũ đồng thời xây dựng sinh kế đủ bền để theo nghề.' },
		],
		quote: 'Nhát đục đẹp nhất là nhát đục biết dừng, vì phần gỗ đã mất thì không thể đặt trở lại.',
		quoteBy: 'Nghệ nhân của xưởng tượng mô phỏng',
		takeaways: [ 'Ghi rõ loại gỗ và nguồn vật liệu.', 'Chụp từng giai đoạn thay vì chỉ thành phẩm.', 'Lưu lại bộ dụng cụ cùng câu chuyện sử dụng.', 'Tạo thời gian thực hành có hướng dẫn cho người trẻ.' ],
	},
	{
		title: 'Dòng sông nhớ mùa lễ hội',
		sapo: 'Một tuyến kể đi dọc bến nước trước, trong và sau ngày hội để nhìn thấy ký ức cộng đồng, áp lực môi trường và những đổi thay ít xuất hiện trong khung hình rực rỡ.',
		setting: 'Bến sông nối khu dân cư với quần thể tự viện, nhiều năm là nơi thuyền hoa tập kết trước giờ nghi lễ bắt đầu.',
		opening: 'Trước ngày hội một tuần, mặt sông còn yên. Trên bờ, nhóm thanh niên đo lại khoảng cách neo thuyền trong khi những người lớn tuổi lần theo danh sách các gia đình từng góp đèn.',
		chapters: [
			{ heading: 'Bến nước trước giờ lên đèn', focus: 'Khâu chuẩn bị hé lộ mạng lưới cộng tác phía sau lễ hội, từ người kết hoa, lái thuyền đến đội thu gom rác và nhóm đảm bảo an toàn trên mặt nước.' },
			{ heading: 'Khi ký ức trôi cùng dòng sông', focus: 'Ảnh tư liệu và lời kể được đối chiếu để cho thấy nghi lễ đã thay đổi về quy mô, vật liệu và cách cộng đồng tham gia qua từng giai đoạn.' },
			{ heading: 'Sáng hôm sau ngày hội', focus: 'Thay vì dừng ở khoảnh khắc lung linh, chương cuối theo đội tình nguyện trở lại bến, đo lượng rác và bàn cách giảm vật liệu dùng một lần cho mùa sau.' },
		],
		quote: 'Muốn giữ vẻ đẹp của đêm hội, trước hết phải chịu trách nhiệm với dòng sông khi ánh đèn đã tắt.',
		quoteBy: 'Điều phối viên môi trường của tuyến bài',
		takeaways: [ 'Ưu tiên vật liệu có thể thu hồi sau nghi lễ.', 'Đánh dấu tuyến thuyền và vùng an toàn.', 'Lưu ảnh cùng mốc thời gian, vị trí.', 'Đo tác động môi trường vào sáng hôm sau.' ],
	},
	{
		title: 'Những người trẻ trở về làng',
		sapo: 'Không phải một cuộc hồi hương lãng mạn, đây là câu chuyện về nhóm bạn trẻ thử biến nhà cộng đồng cũ thành nơi đọc sách, học nghề và gặp gỡ vào mỗi cuối tuần.',
		setting: 'Ngôi làng cách thành phố hai giờ xe, có nhiều căn nhà đóng cửa phần lớn trong năm và một gian sinh hoạt chung đã lâu không sử dụng.',
		opening: 'Ngày trở về đầu tiên, họ không mang theo bản thiết kế. Cả nhóm ngồi dưới hiên, hỏi người già về căn phòng bỏ trống và hỏi trẻ nhỏ điều gì khiến các em muốn quay lại vào tuần sau.',
		chapters: [
			{ heading: 'Trở về không có nghĩa là bắt đầu lại', focus: 'Nhóm trẻ chọn khảo sát nguồn lực sẵn có và nghe nhu cầu địa phương trước khi đề xuất hoạt động, tránh mang một mô hình thành phố áp vào nhịp làng.' },
			{ heading: 'Một căn phòng có nhiều chức năng', focus: 'Tủ sách, bàn học và góc sửa đồ dùng chung được bố trí linh hoạt; cặp ảnh đối chiếu không gian trước và sau giúp câu chuyện thay đổi trở nên đo được.' },
			{ heading: 'Ai sẽ mở cửa vào tháng tới', focus: 'Thử thách không nằm ở ngày khai trương mà ở lịch vận hành, chi phí nhỏ và việc chuyển vai trò từ nhóm khởi xướng sang người sử dụng tại chỗ.' },
		],
		quote: 'Chúng tôi chỉ thật sự trở về khi nơi này có thể hoạt động cả trong những tuần không ai từ thành phố ghé xuống.',
		quoteBy: 'Thành viên nhóm cộng đồng mô phỏng',
		takeaways: [ 'Hỏi nhu cầu trước khi chọn mô hình.', 'Dùng nội thất có thể thay đổi công năng.', 'Theo dõi số người quay lại thay vì lượt ghé đầu tiên.', 'Bàn giao chìa khóa và ngân sách nhỏ cho nhóm tại chỗ.' ],
	},
	{
		title: 'Hành trình của một cuốn kinh xưa',
		sapo: 'Từ hòm gỗ trong kho chùa đến bàn số hóa, một bản kinh cũ mở ra câu chuyện về giấy dó, dấu triện, ký ức người lưu giữ và giới hạn của công nghệ bảo tồn.',
		setting: 'Gian lưu trữ nhỏ nằm ở phía ít nắng của chùa, nơi tài liệu được bọc trong vải và đặt cao để tránh ẩm.',
		opening: 'Cuốn kinh được mở trên lớp giấy đỡ trung tính. Không ai lật trang ngay; nhóm tư liệu quan sát mép giấy, đo độ ẩm và ghi lại vết rách trước khi máy ảnh được đưa vào vị trí.',
		chapters: [
			{ heading: 'Dấu vết trên từng thớ giấy', focus: 'Chất giấy, màu mực, cách đóng quyển và những ghi chú bên lề cung cấp dữ kiện ban đầu, nhưng mỗi suy đoán đều được đánh dấu để chờ chuyên gia xác minh.' },
			{ heading: 'Ánh sáng của phòng số hóa', focus: 'Quy trình chụp ưu tiên an toàn vật lý, màu sắc nhất quán và tên tệp có cấu trúc; ảnh toàn trang luôn đi cùng khung chi tiết để người dùng đọc được cả văn bản lẫn dấu vết vật chất.' },
			{ heading: 'Một bản sao không thay thế bản gốc', focus: 'Tệp số giúp mở rộng tiếp cận nhưng không giải quyết việc chống ẩm, quyền sử dụng hay tri thức cần thiết để đọc văn bản cổ, vì vậy kế hoạch bảo tồn phải đi trên hai đường song song.' },
		],
		quote: 'Số hóa không làm tài liệu bất tử; nó chỉ tạo thêm một con đường để tri thức được tiếp cận và kiểm chứng.',
		quoteBy: 'Nhóm lưu trữ của câu chuyện mô phỏng',
		takeaways: [ 'Đánh giá tình trạng trước khi mở tài liệu.', 'Dùng bảng màu và mã định danh trong mỗi lượt chụp.', 'Lưu bản gốc, bản bảo quản và bản truy cập riêng.', 'Ghi rõ quyền sử dụng cùng nguồn gốc tài liệu.' ],
	},
	{
		title: 'Bếp chay giữa lòng thành phố',
		sapo: 'Giữa khu phố luôn vội, căn bếp nhỏ nấu hàng trăm suất ăn bằng một quy trình không ồn ào: đi chợ theo mùa, chia ca hợp lý và dùng hết từng bó rau.',
		setting: 'Bếp nằm trong một con hẻm hẹp, diện tích không lớn nhưng có luồng di chuyển một chiều từ sơ chế đến đóng hộp.',
		opening: 'Năm giờ sáng, bảng thực đơn được sửa lần cuối theo lượng rau vừa về. Người phụ trách không hỏi món nào chụp ảnh đẹp nhất; chị kiểm tra trước số suất, nhóm chất và thời gian giữ nóng.',
		chapters: [
			{ heading: 'Thực đơn bắt đầu từ phiên chợ', focus: 'Mùa vụ, giá nguyên liệu và khả năng tận dụng phần còn lại quyết định món ăn, giúp bếp cân bằng dinh dưỡng mà không phụ thuộc vào nguyên liệu đắt tiền.' },
			{ heading: 'Một dây chuyền được đo bằng bước chân', focus: 'Cách đặt bàn, nồi và khu rửa được điều chỉnh sau khi nhóm ghi lại các điểm giao cắt; hình ảnh từ trên cao và ảnh cận món ăn bổ sung cho nhau thay vì lặp lại.' },
			{ heading: 'Sau suất ăn cuối cùng', focus: 'Nhóm cân phần thực phẩm dư, ghi phản hồi và chuẩn bị nước dùng cho ngày kế tiếp, biến mục tiêu giảm lãng phí thành dữ liệu có thể theo dõi.' },
		],
		quote: 'Một bữa chay tử tế không cần cầu kỳ, nhưng phải đủ chất, đúng giờ và trân trọng công sức của cả người nấu lẫn người nhận.',
		quoteBy: 'Người phụ trách bếp trong tuyến bài',
		takeaways: [ 'Thiết kế thực đơn theo nguồn rau trong mùa.', 'Tách lối sơ chế và đóng gói.', 'Ghi nhận món được dùng hết hoặc còn dư.', 'Tính cả thời gian của tình nguyện viên vào kế hoạch.' ],
	},
	{
		title: 'Tiếng chuông đi qua bốn mùa',
		sapo: 'Cùng một tháp chuông nhưng mỗi mùa có một âm sắc, một quầng sáng và một nhịp người qua lại khác nhau. Bộ ảnh dài theo dõi không gian ấy suốt một năm.',
		setting: 'Tháp chuông đứng giữa sân cây, đủ gần khu dân cư để âm thanh trở thành một phần của lịch sinh hoạt hằng ngày.',
		opening: 'Mùa xuân, tiếng chuông tan trong mưa bụi. Đến mùa hạ, âm thanh đi xa hơn qua khoảng sân khô, còn người kéo chuông luôn đứng ở cùng một vị trí đã mòn dấu chân.',
		chapters: [
			{ heading: 'Mùa xuân nghe bằng ký ức', focus: 'Lời kể về âm thanh được ghi cùng thời điểm và vị trí, cho thấy mỗi người nhớ tiếng chuông qua một trải nghiệm riêng chứ không phải một biểu tượng trừu tượng.' },
			{ heading: 'Mùa hạ nhìn thấy nhịp lao động', focus: 'Ống kính chuyển từ tháp chuông sang người chăm dây, kiểm tra giá gỗ và dọn sân, làm rõ công việc vật chất giữ cho một nghi thức được tiếp tục.' },
			{ heading: 'Mùa thu, mùa đông và khoảng lặng', focus: 'Khi lượng khách giảm, những khung hình rộng dành chỗ cho sương, lá và khoảng trống; chuỗi ảnh khép lại bằng câu hỏi âm thanh định hình cảm giác về nơi chốn ra sao.' },
		],
		quote: 'Có những ngày tôi không nhớ giờ, chỉ cần nghe chuông là biết buổi chiều đã đi đến đâu.',
		quoteBy: 'Cư dân sống gần tháp chuông mô phỏng',
		takeaways: [ 'Chụp lại cùng một góc qua nhiều mùa.', 'Ghi thời tiết và thời điểm của bản thu.', 'Đưa công việc bảo dưỡng vào tuyến kể.', 'Đặt trải nghiệm âm thanh cạnh hình ảnh tĩnh.' ],
	},
	{
		title: 'Con đường lên ngôi chùa trên núi',
		sapo: 'Hơn một nghìn bậc đá không chỉ dẫn đến điểm ngắm cảnh. Dọc đường là câu chuyện về người gùi hàng, điểm nghỉ, dòng nước và trách nhiệm của mỗi bước chân.',
		setting: 'Lối lên núi bắt đầu sau khu dân cư, đi qua rừng thứ sinh rồi thu hẹp ở đoạn gần cổng chùa.',
		opening: 'Chuyến đi khởi hành khi trời chưa sáng hẳn. Ánh đèn pin quét qua những ký hiệu nhỏ trên đá, còn người gùi hàng chọn nhịp bước chậm đủ để trò chuyện mà không hụt hơi.',
		chapters: [
			{ heading: 'Những bậc đá có người chăm sóc', focus: 'Dấu sơn chỉ đường, rãnh thoát nước và lan can ở khúc cua là kết quả của công việc định kỳ; tuyến ảnh ghi nhận cả người thực hiện thay vì chỉ chụp cảnh quan.' },
			{ heading: 'Một điểm nghỉ, nhiều câu chuyện', focus: 'Tại các mái che nhỏ, khách hành hương gặp người bán nước và nhóm vận chuyển, từ đó bài viết mở rộng sang sinh kế và nguyên tắc giảm rác trên tuyến.' },
			{ heading: 'Đến nơi mà không bỏ lại dấu vết', focus: 'Chương cuối xem xét sức chứa, nguồn nước và phương án mang rác xuống núi, nhấn mạnh trải nghiệm tâm linh không thể tách khỏi trách nhiệm với hệ sinh thái.' },
		],
		quote: 'Đường lên núi dạy người ta đi chậm; đi chậm rồi mới thấy mỗi bậc đá đều có công sức của một người khác.',
		quoteBy: 'Người dẫn đường trong câu chuyện',
		takeaways: [ 'Công bố độ khó và vị trí nghỉ.', 'Mang chai dùng lại và đưa rác xuống núi.', 'Ghi nhận lao động vận chuyển phía sau chuyến đi.', 'Giới hạn nhóm theo sức chứa của tuyến.' ],
	},
	{
		title: 'Phía sau một dự án thiện nguyện',
		sapo: 'Từ cuộc gọi đầu tiên đến buổi đánh giá sáu tháng sau, hồ sơ ảnh theo một dự án nước sạch để nhìn rõ nhu cầu, ngân sách, trách nhiệm bàn giao và tiếng nói người sử dụng.',
		setting: 'Điểm dự án là một cụm dân cư xa nguồn nước ổn định, nơi việc vận chuyển vật tư phụ thuộc nhiều vào thời tiết.',
		opening: 'Bản đề xuất ban đầu chỉ dài hai trang nhưng có một khoảng trống lớn: chưa ai hỏi các hộ gia đình ai sẽ bảo dưỡng hệ thống sau khi đội thi công rời đi.',
		chapters: [
			{ heading: 'Bắt đầu bằng một câu hỏi đúng', focus: 'Nhóm khảo sát chuyển trọng tâm từ món quà có thể trao sang vấn đề người dân ưu tiên, khả năng đóng góp và những cách xử lý nước họ đã thử.' },
			{ heading: 'Ngân sách phải kể được câu chuyện', focus: 'Bảng chi phí được giải thích bằng hạng mục, tuổi thọ và người chịu trách nhiệm; ảnh thi công đi cùng chú thích về tiến độ thay vì trở thành bằng chứng duy nhất của hiệu quả.' },
			{ heading: 'Sáu tháng sau ngày bàn giao', focus: 'Cuộc quay lại đo chất lượng vận hành, thời gian sửa chữa và mức độ sử dụng, cho phép dự án nhìn nhận cả kết quả tốt lẫn điểm thiết kế chưa phù hợp.' },
		],
		quote: 'Một công trình chỉ hoàn thành khi người sử dụng biết gọi ai, làm gì và lấy nguồn lực ở đâu nếu nó ngừng hoạt động.',
		quoteBy: 'Điều phối viên dự án mô phỏng',
		takeaways: [ 'Xác nhận nhu cầu với nhiều nhóm sử dụng.', 'Công khai chi phí vòng đời, không chỉ chi phí lắp đặt.', 'Đào tạo người vận hành tại chỗ.', 'Lên lịch đánh giá sau bàn giao.' ],
	},
	{
		title: 'Khu vườn chữa lành của cộng đồng',
		sapo: 'Một bãi đất nhỏ được biến thành khu vườn chung, nơi người cao tuổi trồng thuốc nam, trẻ em học về côn trùng và những người mệt mỏi tìm lại nhịp thở chậm.',
		setting: 'Khu vườn nằm giữa dãy nhà và sân sinh hoạt, nhận nắng buổi sáng nhưng thường ngập cục bộ sau mưa lớn.',
		opening: 'Luống cây đầu tiên thất bại vì đất giữ quá nhiều nước. Thay vì thay toàn bộ cây, nhóm làm vườn đào một rãnh nhỏ, ghi lại độ ẩm và hỏi người cao tuổi về giống chịu được mùa mưa.',
		chapters: [
			{ heading: 'Học đọc một mảnh đất', focus: 'Ánh sáng, nước, hướng gió và lối tiếp cận được quan sát qua nhiều tuần, giúp thiết kế khu vườn bắt đầu từ điều kiện thật thay vì hình minh họa đẹp.' },
			{ heading: 'Mỗi luống cây có một người kể chuyện', focus: 'Cây thuốc, rau gia vị và hoa bản địa được gắn với ký ức sử dụng; ảnh chân dung và ảnh chi tiết tạo thành cặp để nối tri thức với người trao truyền.' },
			{ heading: 'Chăm vườn cũng là chăm nhau', focus: 'Lịch tưới được thiết kế nhẹ, có ghế nghỉ và phần việc cho nhiều thể trạng, biến khu vườn thành hạ tầng gặp gỡ thay vì thêm một gánh nặng tình nguyện.' },
		],
		quote: 'Khu vườn không chữa thay cho ai, nhưng cho mỗi người một nơi an toàn để bắt đầu chăm lại nhịp sống của mình.',
		quoteBy: 'Người chăm vườn trong câu chuyện',
		takeaways: [ 'Quan sát đất và nước qua ít nhất một mùa.', 'Chọn cây có câu chuyện và công dụng rõ.', 'Thiết kế lối đi cho nhiều khả năng vận động.', 'Chia lịch chăm theo phần việc nhỏ.' ],
	},
	{
		title: 'Mái chùa sau mùa bão',
		sapo: 'Khi nước rút, việc dựng lại một mái chùa không chỉ là thay ngói. Người dân phải phân loại cấu kiện, đọc dấu vết hư hại và quyết định phần nào cần giữ nguyên.',
		setting: 'Công trình nằm ở vùng thường có gió lớn, với mái gỗ nhiều lớp và sân thấp hơn con đường mới nâng cấp.',
		opening: 'Sau bão, hàng ngói vỡ được xếp thành từng nhóm thay vì đổ bỏ. Mỗi mảnh còn hoa văn được đánh số, chụp ảnh và đặt cạnh sơ đồ vị trí trên mái.',
		chapters: [
			{ heading: 'Đọc hiện trường trước khi dọn dẹp', focus: 'Ảnh toàn cảnh xác định hướng tác động, ảnh chi tiết ghi mối nối và vết nứt; việc thu dọn chỉ bắt đầu sau khi thông tin có nguy cơ biến mất được lưu lại.' },
			{ heading: 'Giữa sửa chữa và thay mới', focus: 'Thợ mộc, người trông chùa và kỹ sư cùng đánh giá từng cấu kiện, cân bằng an toàn, tính nguyên gốc và nguồn vật liệu có thể tìm được.' },
			{ heading: 'Chuẩn bị cho mùa mưa kế tiếp', focus: 'Thoát nước, lịch kiểm tra và nơi cất vật tư khẩn cấp được đưa vào kế hoạch, để lần phục hồi này không chỉ trả công trình về trạng thái trước bão.' },
		],
		quote: 'Giữ di sản không có nghĩa là giữ mọi thứ bằng mọi giá; điều quan trọng là hiểu vì sao một phần được giữ và một phần phải thay.',
		quoteBy: 'Nhóm phục hồi công trình mô phỏng',
		takeaways: [ 'Lập hồ sơ ảnh trước khi di chuyển cấu kiện.', 'Đánh số những phần có thể tái sử dụng.', 'Ghi lý do cho mỗi quyết định thay mới.', 'Đưa thích ứng khí hậu vào lịch bảo trì.' ],
	},
	{
		title: 'Những người phụ nữ giữ mùa lễ hội',
		sapo: 'Phía sau sân lễ rực màu là nhiều tuần may cờ, chuẩn bị thực phẩm, tập nghi thức và truyền lại những việc hiếm khi được ghi tên trong chương trình chính thức.',
		setting: 'Nhà sinh hoạt cạnh chùa trở thành xưởng chung trong tháng trước lễ, với vải, sổ tay và dụng cụ được chia thành từng bàn.',
		opening: 'Buổi họp không mở đầu bằng diễn văn. Một người trải tấm vải cũ lên bàn, chỉ vào đường khâu đã sờn và hỏi ai còn nhớ cách ráp mảnh theo đúng thứ tự.',
		chapters: [
			{ heading: 'Công việc không xuất hiện trên sân khấu', focus: 'Danh sách nhiệm vụ cho thấy lễ hội phụ thuộc vào lao động chuẩn bị, chăm sóc và hậu cần; tuyến ảnh chủ động đặt những bàn tay ấy ở trung tâm khung hình.' },
			{ heading: 'Một công thức được truyền bằng cảm giác', focus: 'Cách nêm, gấp vải hay sắp lễ vật thường chưa thành văn bản, vì vậy nhóm trẻ vừa thực hành vừa ghi lại các mốc có thể mô tả mà không làm mất tính linh hoạt.' },
			{ heading: 'Khi tên người làm được ghi lại', focus: 'Chương cuối bàn về quyền tác giả cộng đồng, cách lưu hồ sơ và việc phân chia trách nhiệm công bằng hơn cho mùa lễ tiếp theo.' },
		],
		quote: 'Chúng tôi không cần đứng ở hàng đầu, nhưng câu chuyện của lễ hội sẽ thiếu nếu không ai nhớ những người đã chuẩn bị từ nhiều tuần trước.',
		quoteBy: 'Thành viên tổ hậu cần mô phỏng',
		takeaways: [ 'Ghi tên người đóng góp cùng vai trò.', 'Chụp cả quá trình chuẩn bị.', 'Chuyển kỹ năng truyền miệng thành buổi thực hành.', 'Phân bổ ca trực để tránh phụ thuộc một nhóm.' ],
	},
	{
		title: 'Người chụp lại những ngôi chùa đang đổi thay',
		sapo: 'Mang theo máy ảnh, thước dây và sổ ghi chép, một nhóm tư liệu trở lại cùng địa điểm qua nhiều năm để nhận ra những thay đổi nhỏ trước khi chúng biến mất.',
		setting: 'Tuyến khảo sát đi qua nhiều ngôi chùa quy mô nhỏ, nơi hình ảnh lịch sử thường nằm rải rác trong album gia đình.',
		opening: 'Tấm ảnh cũ không ghi ngày tháng, nhưng bóng đổ của mái và một biển hiệu ở góc khung hình cho nhóm tư liệu vài manh mối để bắt đầu đối chiếu.',
		chapters: [
			{ heading: 'Một bức ảnh cần nhiều hơn góc đẹp', focus: 'Mỗi khung hình được gắn mã địa điểm, hướng chụp và thời điểm; ảnh tổng thể hỗ trợ so sánh trong khi ảnh chi tiết giữ lại vật liệu và dấu hiệu xuống cấp.' },
			{ heading: 'Trở lại đúng vị trí cũ', focus: 'Việc tái chụp cùng góc giúp nhìn rõ cây xanh, công trình phụ và cách sử dụng sân đã thay đổi, tạo dữ liệu thay vì chỉ dựa vào ấn tượng.' },
			{ heading: 'Trao bản lưu về nơi nó thuộc về', focus: 'Nhóm không giữ toàn bộ tư liệu trên máy cá nhân mà bàn giao bản sao, hướng dẫn tra cứu và thống nhất quyền công bố với cộng đồng sở hữu ký ức.' },
		],
		quote: 'Ảnh tư liệu có giá trị khi người khác biết nó được chụp ở đâu, khi nào và có thể tìm lại bản gốc bằng cách nào.',
		quoteBy: 'Nhiếp ảnh gia tư liệu trong tuyến bài',
		takeaways: [ 'Luôn ghi vị trí và hướng máy.', 'Giữ cả ảnh toàn cảnh lẫn chi tiết.', 'Đặt quy tắc tên tệp trước chuyến đi.', 'Bàn giao ít nhất một bản lưu cho cộng đồng.' ],
	},
	{
		title: 'Lớp học chữ cũ bên hiên chùa',
		sapo: 'Mỗi cuối tuần, những trang chữ Hán Nôm được đặt cạnh bản dịch và câu chuyện địa phương. Lớp học nhỏ thử nối kỹ năng đọc văn bản với nhu cầu hiểu di sản quanh mình.',
		setting: 'Lớp học dùng một gian hiên thoáng, nơi người lớn tuổi, sinh viên và học sinh ngồi chung quanh những bản sao khổ lớn.',
		opening: 'Bài học đầu tiên không yêu cầu nhớ mặt chữ. Người hướng dẫn đưa ra ảnh một tấm biển trong chùa và hỏi cả lớp điều gì có thể nhận biết trước khi đọc nội dung.',
		chapters: [
			{ heading: 'Bắt đầu từ một văn bản gần nhà', focus: 'Địa danh, niên đại và bố cục giúp người mới có điểm tựa; bản sao chất lượng cao bảo vệ tài liệu gốc trong khi vẫn giữ được dấu vết cần quan sát.' },
			{ heading: 'Dịch nghĩa là cùng nhau kiểm tra', focus: 'Mỗi cách đọc được ghi cùng lý do và nguồn tham khảo, tạo không gian cho bất đồng học thuật mà không biến giả thuyết thành kết luận chắc chắn.' },
			{ heading: 'Khi bài học trở lại di tích', focus: 'Học viên thử viết chú giải ngắn, quét mã liên kết đến bản đầy đủ và nhận phản hồi từ người địa phương về ngôn ngữ dễ hiểu.' },
		],
		quote: 'Đọc được một chữ là mở được một cánh cửa, nhưng muốn hiểu căn phòng phía sau phải hỏi thêm lịch sử và người đang sống cùng di sản.',
		quoteBy: 'Người hướng dẫn lớp học mô phỏng',
		takeaways: [ 'Dùng bản sao khi tài liệu gốc dễ tổn thương.', 'Ghi nguồn cho từng phương án đọc.', 'Phân biệt phiên âm, dịch nghĩa và diễn giải.', 'Thử chú giải với người đọc không chuyên.' ],
	},
	{
		title: 'Một đêm ở ngôi chùa không ngủ',
		sapo: 'Khi thành phố tắt bớt ánh đèn, ngôi chùa bước vào một ca làm việc khác: đón người lỡ đường, chuẩn bị khóa lễ sớm và giữ yên cho những người cần nghỉ.',
		setting: 'Ngôi chùa nằm gần bến xe, ban ngày đông khách nhưng sau nửa đêm chỉ còn ánh sáng ở gian trực và khu bếp.',
		opening: 'Mười một giờ đêm, cổng phụ vẫn mở vừa đủ một người đi qua. Cuốn sổ trực ghi số chăn còn lại, bình nước nóng và tên người sẽ nhận ca lúc hai giờ sáng.',
		chapters: [
			{ heading: 'Những vị khách đến sau giờ đóng cửa', focus: 'Quy trình tiếp nhận ưu tiên an toàn, sự riêng tư và nhu cầu thiết yếu, cho thấy lòng hiếu khách cần ranh giới rõ để bảo vệ cả người đến lẫn người trực.' },
			{ heading: 'Nhịp làm việc dưới ánh đèn thấp', focus: 'Ảnh đêm tập trung vào khoảng sáng, bàn tay và vật dụng thay vì xâm phạm khoảnh khắc riêng; chú thích giải thích hoạt động mà không nhận diện người cần hỗ trợ.' },
			{ heading: 'Bình minh bắt đầu từ ca đêm', focus: 'Trước tiếng chuông sáng, khu bếp và sân đã được bàn giao; chương cuối cho thấy một ngày trôi chảy nhờ những công việc phần lớn diễn ra ngoài tầm nhìn.' },
		],
		quote: 'Giữ cửa mở không chỉ là để một ngọn đèn sáng; đó là biết đón ai, hỗ trợ đến đâu và khi nào cần tìm thêm sự giúp đỡ.',
		quoteBy: 'Người trực đêm trong câu chuyện',
		takeaways: [ 'Có quy trình tiếp nhận và chuyển tuyến rõ ràng.', 'Không chụp nhận diện người đang cần hỗ trợ.', 'Bàn giao đủ thông tin giữa các ca.', 'Thiết kế lịch trực có thời gian nghỉ.' ],
	},
	{
		title: 'Mạch nước trên sườn núi',
		sapo: 'Một đường ống nhỏ nối nguồn nước với tự viện và khu dân cư phía dưới. Theo dòng chảy ấy là bài toán mùa khô, chia sẻ nguồn lực và bảo vệ khu rừng đầu nguồn.',
		setting: 'Nguồn nước xuất phát từ khe đá trong vùng cây bản địa, giảm mạnh vào cuối mùa khô và dễ đục sau mưa lớn.',
		opening: 'Người kiểm tra mở nắp bể lúc sáu giờ sáng, ghi mực nước bằng một vạch sơn rồi đi ngược đường ống để tìm đoạn vừa bị đất đá làm lệch.',
		chapters: [
			{ heading: 'Đi ngược dòng để hiểu nguồn nước', focus: 'Tuyến khảo sát ghi độ dốc, điểm rò và vùng cây che phủ, liên kết hạ tầng nhỏ với hệ sinh thái thay vì xem nước là nguồn cung không giới hạn.' },
			{ heading: 'Một lịch chia nước được cùng viết', focus: 'Tự viện và các hộ dân thống nhất ngưỡng sử dụng trong mùa khô, người theo dõi và cách thông báo khi lưu lượng thay đổi.' },
			{ heading: 'Giữ rừng là giữ phần đầu của đường ống', focus: 'Việc trồng bổ sung, hạn chế xáo trộn đất và quan sát sau mưa được coi là chi phí vận hành thiết yếu, không phải hoạt động bên lề.' },
		],
		quote: 'Đường ống có thể sửa trong một ngày, nhưng nếu vùng giữ nước mất đi thì không có vật liệu nào thay thế được.',
		quoteBy: 'Người theo dõi nguồn nước mô phỏng',
		takeaways: [ 'Ghi lưu lượng theo mùa.', 'Đánh dấu và kiểm tra điểm nối định kỳ.', 'Thống nhất thứ tự ưu tiên khi thiếu nước.', 'Bảo vệ thảm thực vật quanh nguồn.' ],
	},
	{
		title: 'Phiên chợ chay của khu phố',
		sapo: 'Mỗi tháng một lần, sân chung trở thành phiên chợ chay không nhựa dùng một lần, nơi món ăn, câu chuyện nguyên liệu và cách tổ chức cùng được đem ra thử nghiệm.',
		setting: 'Phiên chợ dùng sân có mái che giữa khu phố, với các quầy do gia đình và nhóm thiện nguyện luân phiên phụ trách.',
		opening: 'Khách đầu tiên mang theo hộp đựng, nhưng người bán vẫn chuẩn bị một chồng bát dùng lại cho những ai đi ngang chưa biết quy ước của phiên chợ.',
		chapters: [
			{ heading: 'Mỗi quầy kể một câu chuyện nguyên liệu', focus: 'Bảng nhỏ ghi nguồn rau, thành phần và dị ứng thường gặp; ảnh món ăn đi cùng chân dung người nấu để nối hương vị với kinh nghiệm gia đình.' },
			{ heading: 'Không rác là một hệ thống', focus: 'Điểm mượn bát, khu rửa và luồng thu hồi được thiết kế cùng nhau, tránh biến trách nhiệm giảm rác thành yêu cầu chỉ dành cho khách.' },
			{ heading: 'Sau ba giờ họp chợ', focus: 'Nhóm cân phần hữu cơ, đếm vật dụng thất lạc và hỏi quầy nào còn dư nhiều, từ đó điều chỉnh quy mô cho tháng kế tiếp.' },
		],
		quote: 'Một phiên chợ xanh không được đo bằng tấm biển đẹp, mà bằng số món đồ thật sự quay lại vòng sử dụng.',
		quoteBy: 'Điều phối viên phiên chợ mô phỏng',
		takeaways: [ 'Công bố thành phần và nguồn nguyên liệu.', 'Tổ chức điểm mượn, trả và rửa thống nhất.', 'Đo rác sau mỗi phiên.', 'Điều chỉnh số suất theo dữ liệu bán thực tế.' ],
	},
	{
		title: 'Gỗ cũ kể chuyện trùng tu',
		sapo: 'Trong kho cấu kiện, những thanh gỗ cong, mộng nối và lớp sơn bong trở thành hồ sơ vật chất giúp đội trùng tu quyết định điều gì có thể cứu và điều gì phải thay.',
		setting: 'Kho tạm được dựng cạnh công trình, chia khu khô, khu chờ đánh giá và bàn ghi chép có bản vẽ từng gian.',
		opening: 'Một thanh xà được đặt lên giá thấp. Dưới ánh sáng xiên, vết mối và đường mực của người thợ cũ cùng hiện ra, buộc nhóm đánh giá nhìn cả hư hại lẫn giá trị thông tin.',
		chapters: [
			{ heading: 'Mỗi cấu kiện có một căn cước', focus: 'Mã vị trí, kích thước, loại gỗ và tình trạng được ghi trước khi tháo dỡ, giúp vật liệu không mất ngữ cảnh khi rời khỏi công trình.' },
			{ heading: 'Sửa một mộng nối, giữ một dấu tay', focus: 'Thợ ưu tiên can thiệp tối thiểu ở phần còn đủ khả năng chịu lực; ảnh macro cho thấy ranh giới giữa bề mặt cũ và phần gia cường mới.' },
			{ heading: 'Hồ sơ ở lại sau giàn giáo', focus: 'Bản vẽ, ảnh và lý do quyết định được đóng gói thành hồ sơ có thể truy cập, để lần bảo trì sau không phải đoán những gì từng xảy ra.' },
		],
		quote: 'Một thanh gỗ cũ không chỉ là vật liệu; nó còn giữ thông tin về công cụ, bàn tay và quyết định của những lần sửa trước.',
		quoteBy: 'Thợ mộc bảo tồn trong tuyến bài',
		takeaways: [ 'Gắn mã trước khi tháo cấu kiện.', 'Chụp đủ bốn mặt và vị trí mộng.', 'Phân biệt phần cũ với vật liệu bổ sung.', 'Lưu lý do kỹ thuật của từng can thiệp.' ],
	},
	{
		title: 'Thư viện nhỏ sau cổng chùa',
		sapo: 'Từ vài thùng sách quyên góp, nhóm tình nguyện xây một thư viện mở có giờ đọc cho trẻ em, góc yên tĩnh cho người lớn và quy trình chọn sách minh bạch.',
		setting: 'Phòng đọc từng là kho, có cửa nhìn ra sân và đủ chỗ cho các kệ thấp chạy dọc hai bên tường.',
		opening: 'Thùng sách đầu tiên có nhiều bản trùng và vài cuốn đã ẩm. Nhóm không vội xếp lên kệ mà phân loại, ghi tình trạng và hỏi người đọc trong khu phố đang tìm nội dung gì.',
		chapters: [
			{ heading: 'Một tủ sách bắt đầu từ người đọc', focus: 'Khảo sát ngắn về độ tuổi, ngôn ngữ và thời gian ghé giúp nhóm xây danh mục phù hợp thay vì đo thành công bằng số lượng sách nhận được.' },
			{ heading: 'Căn phòng khuyến khích ở lại', focus: 'Kệ thấp, ánh sáng đọc và khoảng trống linh hoạt tạo nhiều mức yên tĩnh; ảnh rộng cho thấy luồng không gian còn ảnh cặp ghi chi tiết cách trẻ sử dụng.' },
			{ heading: 'Để thư viện không khóa cửa', focus: 'Lịch trực, quy tắc mượn đơn giản và quỹ mua sách nhỏ được chia sẻ, giúp hoạt động không phụ thuộc hoàn toàn vào một người yêu sách.' },
		],
		quote: 'Sách được tặng chỉ trở thành thư viện khi có người đọc, người chăm và một cách rõ ràng để cuốn tiếp theo tìm đến đúng kệ.',
		quoteBy: 'Tình nguyện viên thư viện mô phỏng',
		takeaways: [ 'Khảo sát nhu cầu trước khi nhận sách số lượng lớn.', 'Lọc bản hỏng, trùng và không phù hợp.', 'Thiết kế kệ trong tầm với của trẻ.', 'Duy trì ngân sách bổ sung đầu sách còn thiếu.' ],
	},
	{
		title: 'Đội tình nguyện số hóa ký ức làng',
		sapo: 'Những album gia đình, băng cassette và tờ chương trình cũ được mang đến một ngày hội số hóa, nơi công nghệ đi cùng thỏa thuận về quyền riêng tư và cách trả dữ liệu.',
		setting: 'Trạm số hóa đặt tại nhà cộng đồng, chia thành bàn tiếp nhận, khu chụp tài liệu phẳng, máy ghi âm và bàn trả bản gốc.',
		opening: 'Người phụ nữ đầu tiên mang đến một phong bì ảnh không ghi năm. Trước khi quét, tình nguyện viên hỏi bà muốn giữ ảnh nào trong phạm vi gia đình và ảnh nào có thể đưa vào kho chung.',
		chapters: [
			{ heading: 'Xin phép trước khi nhấn nút quét', focus: 'Phiếu tiếp nhận dùng ngôn ngữ dễ hiểu, cho phép chủ sở hữu chọn mức chia sẻ và thay đổi quyết định sau đó, đặt sự đồng thuận trước tốc độ thu thập.' },
			{ heading: 'Tệp số cũng cần một câu chuyện', focus: 'Tên người, địa điểm, thời gian ước đoán và mức độ chắc chắn được ghi cùng tệp; nếu thiếu ngữ cảnh, hình ảnh đẹp vẫn khó tìm và dễ bị diễn giải sai.' },
			{ heading: 'Trả lại nhiều hơn một chiếc phong bì', focus: 'Người góp tư liệu nhận bản sao có hướng dẫn mở, còn kho cộng đồng giữ cấu trúc thư mục, bản sao dự phòng và đầu mối xử lý yêu cầu chỉnh sửa.' },
		],
		quote: 'Số hóa ký ức không phải là lấy tài liệu khỏi gia đình, mà là cùng họ tạo một bản sao có ngữ cảnh và quyền lựa chọn.',
		quoteBy: 'Điều phối viên lưu trữ cộng đồng mô phỏng',
		takeaways: [ 'Ghi mức đồng thuận cho từng tài liệu.', 'Không tách tệp khỏi thông tin người cung cấp.', 'Trả bản sao ở định dạng dễ sử dụng.', 'Duy trì ít nhất hai bản sao ở hai nơi.' ],
	},
];

if ( emagazineStories.length !== 20 ) {
	throw new Error( `Expected 20 E-magazine story profiles, received ${ emagazineStories.length }.` );
}

const emagazineChapterHeadings = emagazineStories.flatMap( ( story ) => story.chapters.map( ( item ) => item.heading ) );
if (
	new Set( emagazineStories.map( ( story ) => story.title ) ).size !== 20 ||
	new Set( emagazineStories.map( ( story ) => story.sapo ) ).size !== 20 ||
	new Set( emagazineStories.map( ( story ) => story.quote ) ).size !== 20 ||
	new Set( emagazineChapterHeadings ).size !== 60
) {
	throw new Error( 'E-magazine story profiles must have unique titles, sapos, quotes and chapter headings.' );
}

const categoryDetails = {
	'tin-phat-su': {
		heading: 'Thông tin sự kiện và trách nhiệm xác minh',
		paragraph: 'Với một bản tin Phật sự, mốc thời gian, địa điểm, đơn vị tổ chức và quy mô tham dự cần được đặt trong cùng một mạch thông tin. Fixture vì vậy mô phỏng cả phần chuẩn bị, diễn biến và công việc nối tiếp, giúp biên tập viên kiểm tra sapo, trật tự dữ kiện và cách bài liên kết đến hoạt động cộng đồng.',
	},
	'song-an-lanh': {
		heading: 'Một thực hành có thể lặp lại',
		paragraph: 'Nội dung Sống an lành tránh những lời khuyên tuyệt đối. Mỗi bài chọn một thực hành nhỏ, mô tả hoàn cảnh áp dụng, dấu hiệu cần điều chỉnh và cách tự quan sát sau vài ngày. Nhờ đó người đọc có thể thử nghiệm trong đời sống thật mà không xem bài viết như tư vấn y khoa hoặc một công thức phù hợp với tất cả mọi người.',
	},
	'am-thuc-chay': {
		heading: 'Từ nguyên liệu đến một bữa ăn cân bằng',
		paragraph: 'Bài Ẩm thực chay chú ý mùa vụ, nhóm chất, khẩu phần và cách tận dụng nguyên liệu còn lại. Các bước chế biến được kể cùng bối cảnh gia đình hoặc bếp cộng đồng, để ảnh món ăn không đứng riêng khỏi câu chuyện dinh dưỡng, chi phí và giảm lãng phí trong căn bếp hằng ngày.',
	},
	'loi-song-xanh': {
		heading: 'Đo tác động bằng thay đổi cụ thể',
		paragraph: 'Một lựa chọn xanh chỉ có ý nghĩa khi có thể duy trì. Bài viết ghi nhận lượng vật dụng được giảm, nguồn lực cần thiết, người chịu trách nhiệm và trở ngại sau giai đoạn thử nghiệm. Góc nhìn này giúp chuyên mục không dừng ở khẩu hiệu, đồng thời tạo dữ liệu phong phú cho danh sách, tìm kiếm và bài liên quan.',
	},
	'phat-tich': {
		heading: 'Đọc di sản từ nhiều lớp tư liệu',
		paragraph: 'Nội dung Phật tích phân biệt điều quan sát tại hiện trường, ký ức truyền miệng và dữ kiện cần đối chiếu từ tư liệu. Chi tiết về vật liệu, niên đại, lần trùng tu và không gian cảnh quan được sắp xếp để người đọc hiểu giá trị di sản, thay vì chỉ nhìn công trình như một phông nền đẹp cho hình ảnh.',
	},
	'tot-doi-dep-dao': {
		heading: 'Phụng sự với mục tiêu và điểm dừng rõ ràng',
		paragraph: 'Bài Tốt đời – đẹp đạo theo dõi nhu cầu của người nhận, cách huy động nguồn lực và cơ chế phản hồi sau chương trình. Fixture chủ động mô tả giới hạn, chi phí duy trì và phương án bàn giao, qua đó giúp kiểm tra một bài xã hội có chiều sâu mà vẫn tôn trọng phẩm giá của cộng đồng được hỗ trợ.',
	},
	'emagazine': {
		heading: 'Một tuyến kể chuyện bằng văn bản và hình ảnh',
		paragraph: 'E-magazine được tổ chức như một hành trình thị giác: cảnh mở đầu tạo không khí, chân dung đưa câu chuyện đến gần con người, còn tư liệu và chi tiết không gian cung cấp chiều sâu. Ảnh rộng, ảnh toàn màn hình, chú thích, gallery và pull quote đều là thành phần nội dung có chủ ý chứ không chỉ để trang trí.',
	},
};

const categories = [
	{
		slug: 'tin-phat-su',
		label: 'Tin Phật sự',
		parent: '',
		focus: 'hoạt động Phật sự, tự viện và cộng đồng Phật tử',
		subjects: [
			'Đại lễ Phật đản trong không gian di sản',
			'Mùa an cư và nhịp sinh hoạt tại tự viện',
			'Khóa tu mùa hè dành cho thanh thiếu niên',
			'Ngày hội văn hóa Phật giáo tại địa phương',
			'Chương trình giao lưu giữa các thế hệ Phật tử',
			'Lễ tưởng niệm và hoạt động tri ân cộng đồng',
			'Tọa đàm về truyền thông Phật giáo trong thời đại số',
			'Tuần lễ đọc sách và tìm hiểu giáo lý',
			'Hành trình kết nối các đạo tràng trẻ',
			'Không gian sinh hoạt mới của một ngôi chùa quê',
		],
	},
	{
		slug: 'song-an-lanh',
		label: 'Sống an lành',
		parent: '',
		focus: 'thực hành chánh niệm và xây dựng nhịp sống cân bằng',
		subjects: [
			'Một buổi sáng dành cho sự tĩnh lặng',
			'Thực tập lắng nghe trong gia đình',
			'Bảy ngày sắp xếp lại nhịp sống',
			'Khoảng nghỉ ngắn giữa ngày làm việc',
			'Đi bộ chánh niệm trong thành phố',
			'Giữ bình tâm trước một cuộc trò chuyện khó',
			'Tạo góc đọc sách và thiền tập tại nhà',
			'Học cách nhận biết cảm xúc đang đến',
			'Một cuối tuần không lịch trình dày đặc',
			'Nuôi dưỡng thói quen biết ơn mỗi tối',
		],
	},
	{
		slug: 'am-thuc-chay',
		label: 'Ẩm thực chay',
		parent: 'song-an-lanh',
		focus: 'ẩm thực chay theo mùa, đủ dinh dưỡng và giảm lãng phí',
		subjects: [
			'Mâm cơm chay theo mùa cho gia đình',
			'Bữa sáng thực vật nhanh và đủ chất',
			'Rau củ địa phương trong căn bếp nhỏ',
			'Nấu ăn chay cho người mới bắt đầu',
			'Một tuần chuẩn bị thực phẩm không lãng phí',
			'Món chay truyền thống trong ngày lễ',
			'Đạm thực vật và cách phối hợp nguyên liệu',
			'Bếp chay cộng đồng phục vụ người khó khăn',
			'Gia vị tự nhiên cho món ăn thanh nhẹ',
			'Bữa cơm chung kết nối ba thế hệ',
		],
	},
	{
		slug: 'loi-song-xanh',
		label: 'Lối sống xanh',
		parent: 'song-an-lanh',
		focus: 'lựa chọn tiêu dùng có trách nhiệm và chăm sóc môi trường sống',
		subjects: [
			'Ngày không nhựa dùng một lần tại tự viện',
			'Khu vườn nhỏ nuôi dưỡng thói quen xanh',
			'Phân loại rác từ những bước đầu tiên',
			'Sửa chữa đồ cũ thay vì mua mới',
			'Tiết kiệm nước trong sinh hoạt hằng ngày',
			'Tủ đồ tối giản và lựa chọn có ý thức',
			'Đi chợ với túi dùng nhiều lần',
			'Không gian xanh cho khu dân cư đông đúc',
			'Chuyến đi gần nhà giảm dấu chân carbon',
			'Nhóm trẻ cùng làm sạch đường làng',
		],
	},
	{
		slug: 'phat-tich',
		label: 'Phật tích',
		parent: '',
		focus: 'di sản kiến trúc, lịch sử tự viện và ký ức của cộng đồng',
		subjects: [
			'Mái ngói cổ và dấu vết thời gian',
			'Chuông chùa trong ký ức một vùng quê',
			'Hành trình đọc lại văn bia cổ',
			'Ngôi chùa ven sông qua nhiều lần trùng tu',
			'Vườn tháp và câu chuyện truyền thừa',
			'Bảo tồn tượng gỗ trong điều kiện khí hậu ẩm',
			'Con đường hành hương qua miền di sản',
			'Nghệ thuật chạm khắc trên cửa chùa',
			'Không gian sân chùa trong đời sống làng',
			'Tư liệu ảnh về một cổ tự miền Trung',
		],
	},
	{
		slug: 'tot-doi-dep-dao',
		label: 'Tốt đời – đẹp đạo',
		parent: '',
		focus: 'hoạt động thiện nguyện, giáo dục và phụng sự xã hội',
		subjects: [
			'Bếp ăn sẻ chia tại bệnh viện',
			'Điểm đọc sách miễn phí cho trẻ em',
			'Chương trình nước sạch ở vùng cao',
			'Nhóm tình nguyện sửa nhà sau bão',
			'Ngày hội hiến máu của Phật tử trẻ',
			'Lớp kỹ năng số dành cho người cao tuổi',
			'Quỹ học bổng tiếp sức học sinh khó khăn',
			'Chuyến xe đưa người bệnh về quê',
			'Vườn rau cộng đồng hỗ trợ bữa ăn',
			'Người trẻ phục dựng sân chơi trong xóm',
		],
	},
	{
		slug: 'emagazine',
		label: 'E-magazine',
		parent: 'media',
		focus: 'câu chuyện dài giàu hình ảnh về văn hóa và đời sống Phật giáo',
		subjects: [
			'E-magazine: Một ngày trong ngôi chùa cổ',
			'E-magazine: Theo dấu người giữ nghề làm tượng',
			'E-magazine: Dòng sông và ký ức mùa lễ hội',
			'E-magazine: Những người trẻ trở về làng',
			'E-magazine: Hành trình của một cuốn kinh xưa',
			'E-magazine: Bếp chay giữa lòng thành phố',
			'E-magazine: Tiếng chuông qua bốn mùa',
			'E-magazine: Con đường lên ngôi chùa trên núi',
			'E-magazine: Phía sau một dự án thiện nguyện',
			'E-magazine: Khu vườn chữa lành của cộng đồng',
		],
	},
	{
		slug: 'video',
		label: 'Video',
		parent: 'media',
		focus: 'phóng sự, hướng dẫn thực hành và câu chuyện kể bằng hình ảnh',
		subjects: [
			'Video: Toàn cảnh ngày hội văn hóa Phật giáo',
			'Video: Mười lăm phút thực tập hơi thở',
			'Video: Khám phá một ngôi chùa ven biển',
			'Video: Nghệ thuật lắng nghe chân thành',
			'Video: Theo chân bếp ăn thiện nguyện',
			'Video: Câu chuyện bảo tồn mái chùa cổ',
			'Video: Một ngày sống xanh tại tự viện',
			'Video: Người trẻ học viết thư pháp',
			'Video: Hành trình trao sách vùng cao',
			'Video: Bữa cơm chay đủ chất cho gia đình',
		],
	},
	{
		slug: 'vietnam-buddhism',
		label: 'Vietnam Buddhism',
		parent: '',
		language: 'en',
		focus: 'Vietnamese Buddhist heritage, practice and contemporary community life',
		subjects: [
			'Buddhist Heritage Along the Perfume River',
			'How Young Volunteers Support Temple Communities',
			'A Practical Introduction to Walking Meditation',
			'Vegetarian Food Traditions in Vietnamese Families',
			'Preserving Historic Pagodas in a Changing Climate',
			'Community Libraries and Buddhist Learning',
			'The Role of Calligraphy in Festival Culture',
			'Listening Practices Across Generations',
			'Women Sustaining Local Buddhist Networks',
			'A Visual Journey Through Rural Temple Life',
		],
	},
];

function slugify( value ) {
	return value
		.normalize( 'NFD' )
		.replace( /[\u0300-\u036f]/g, '' )
		.replace( /đ/g, 'd' )
		.replace( /Đ/g, 'D' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-|-$/g, '' );
}

function buildEmagazineBody( story, index ) {
	const chapter = ( number, heading ) => [
		'<!-- wp:group {"className":"pgds-emagazine-chapter"} -->',
		'<div class="wp-block-group pgds-emagazine-chapter">',
		'<!-- wp:group {"className":"pgds-emagazine-chapter__marker","layout":{"type":"flex","flexWrap":"nowrap"}} -->',
		'<div class="wp-block-group pgds-emagazine-chapter__marker">',
		'<!-- wp:paragraph {"className":"pgds-emagazine-chapter__number"} -->',
		`<p class="pgds-emagazine-chapter__number">${ number }</p>`,
		'<!-- /wp:paragraph -->',
		'</div>',
		'<!-- /wp:group -->',
		'<!-- wp:heading -->',
		`<h2 class="wp-block-heading">${ heading }</h2>`,
		'<!-- /wp:heading -->',
		'</div>',
		'<!-- /wp:group -->',
	].join( '\n' );
	const paragraph = ( text ) => `<!-- wp:paragraph -->\n<p>${ text }</p>\n<!-- /wp:paragraph -->`;
	const quote = `<!-- wp:quote {"className":"is-style-plain pgds-emagazine-pull-quote"} -->\n<blockquote class="wp-block-quote is-style-plain pgds-emagazine-pull-quote"><!-- wp:paragraph -->\n<p>${ story.quote }</p>\n<!-- /wp:paragraph --><cite>${ story.quoteBy }</cite></blockquote>\n<!-- /wp:quote -->`;
	const list = `<!-- wp:list -->\n<ul class="wp-block-list">${ story.takeaways.map( ( item ) => `<!-- wp:list-item --><li>${ item }</li><!-- /wp:list-item -->` ).join( '' ) }</ul>\n<!-- /wp:list -->`;
	const intro = [
		paragraph( `<strong>${ story.title }</strong> bắt đầu bằng một cảnh quan sát cụ thể. ${ story.opening } Nhịp mở chậm để người đọc kịp nhận ra âm thanh, chất liệu và những chuyển động nhỏ trước khi câu chuyện đi vào vấn đề lớn hơn. Đây là tuyến nội dung phát triển hư cấu, nhưng cách thu thập dữ kiện, tổ chức hình ảnh và ghi nguồn được mô phỏng theo một hồ sơ báo chí dài.` ),
		paragraph( `${ story.setting } Nhóm biên tập dựng tuyến kể từ sổ ghi chép, bản đồ công việc và các cuộc trò chuyện không định danh. Điều nhìn thấy tại hiện trường được tách khỏi ký ức truyền miệng và nhận định cần kiểm chứng. Nhờ vậy bài viết vẫn có cảm giác gần gũi mà không biến một chi tiết chưa rõ nguồn thành sự thật. Mỗi nhân vật xuất hiện qua vai trò, lựa chọn và công việc của họ, thay vì chỉ làm lời dẫn cho một thông điệp có sẵn.` ),
	];
	const sections = story.chapters.map( ( item, chapterIndex ) => [
		chapter( String( chapterIndex + 1 ).padStart( 2, '0' ), item.heading ),
		paragraph( `${ item.focus } Ở lớp thông tin đầu tiên, nhóm thực hiện ghi thời gian, vị trí, người chịu trách nhiệm và dấu hiệu có thể quan sát. Những câu hỏi chưa trả lời được giữ lại trong sổ biên tập. Cách làm đó tạo ra độ tin cậy cần thiết cho một bài kể giàu cảm xúc: người đọc biết đâu là dữ kiện, đâu là trải nghiệm cá nhân và đâu là điều đang được cộng đồng tiếp tục thảo luận.` ),
		paragraph( `Ống kính thay đổi khoảng cách theo nhịp của chương ${ chapterIndex + 1 }: toàn cảnh thiết lập quan hệ giữa con người với không gian, trung cảnh theo một hành động trọn vẹn, còn ảnh cận giữ lại dấu tay, bề mặt và công cụ dễ bị bỏ qua. Song song với hình ảnh, phần chữ giải thích nguyên nhân và hệ quả thay vì mô tả lại những gì đã thấy. Chú thích bổ sung bối cảnh, tác giả và nguồn, để ảnh có thể đứng vững như một phần của lập luận biên tập.` ),
	] );
	const wideBeat = [
		'<!--pgds-preview-emag-wide-->',
		paragraph( `Khung ảnh rộng mở trường nhìn đúng lúc câu chuyện cần rời khỏi một chi tiết riêng lẻ. Trong bối cảnh ${ story.title.toLowerCase() }, không gian, đường di chuyển và khoảng cách giữa các nhóm cùng xuất hiện để người đọc hiểu điều kiện tạo nên hoạt động. Ảnh không được chọn chỉ vì đẹp; nó trả lời câu hỏi mà cột chữ hẹp khó truyền đạt, đồng thời tạo một nhịp nghỉ trước khi tuyến kể quay lại nhân vật.` ),
	];
	const galleryBeat = [
		'<!--pgds-preview-emag-gallery-->',
		paragraph( 'Cặp ảnh được biên tập như một phép đối chiếu có chủ ý. Khung thứ nhất cho biết việc gì đang diễn ra và ai tham gia; khung thứ hai tiến gần vào vật liệu, công cụ hoặc biểu cảm quyết định ý nghĩa của cảnh. Thứ tự được giữ khi ảnh xếp thành một cột trên điện thoại, nên câu chuyện vẫn đi từ bối cảnh đến chi tiết. Hai chú thích độc lập tránh việc một lời giải thích chung làm mất thông tin riêng của từng khung hình.' ),
	];
	const fullBeat = [
		'<!--pgds-preview-emag-full-->',
		paragraph( 'Ảnh toàn chiều rộng tạo một khoảng lặng thay vì đóng vai trò minh họa kết bài. Khi hình ảnh thoát khỏi cột đọc, tỷ lệ của cảnh quan và nhịp người trong không gian trở nên rõ hơn; sau đó văn bản trở lại với một câu hỏi cụ thể còn bỏ ngỏ. Trên màn hình nhỏ, hình ảnh co về chiều rộng thiết bị, caption vẫn đi cùng ảnh và không xuất hiện thanh cuộn ngang. Sự chuyển tỷ lệ vì thế phục vụ mạch kể trên cả desktop lẫn mobile.' ),
	];
	const ending = [
		list,
		paragraph( `Bốn ghi chú trên không phải công thức áp dụng cho mọi nơi. Chúng là những việc có thể kiểm tra sau khi câu chuyện kết thúc: ai giữ hồ sơ, thay đổi nào đã xảy ra, nguồn lực nào còn thiếu và người sử dụng có thực sự thấy hữu ích hay không. Với <em>${ story.title }</em>, giá trị của hành trình nằm ở khả năng nối một khoảnh khắc giàu hình ảnh với công việc tiếp diễn phía sau, để cảm xúc không che khuất trách nhiệm và dữ kiện không làm mất đi tiếng nói con người.` ),
		paragraph( 'Tuyến bài khép lại nhưng không tuyên bố mọi vấn đề đã được giải quyết. Một số câu hỏi cần thêm tư liệu, một số thử nghiệm phải đi qua nhiều mùa, và vài quyết định chỉ có thể được đưa ra bởi chính cộng đồng liên quan. Cách kết mở cho phép người đọc mang theo một chi tiết cụ thể, đồng thời giúp biên tập viên tiếp tục cập nhật bài khi có ảnh, lời kể hoặc kết quả mới. Đó cũng là phép thử quan trọng của E-magazine: đẹp về thị giác nhưng vẫn đủ cấu trúc để sống lâu như một hồ sơ nội dung.' ),
	];
	const layouts = [
		[ ...intro, ...sections[ 0 ], ...wideBeat, quote, ...sections[ 1 ], ...galleryBeat, ...sections[ 2 ], ...fullBeat, ...ending ],
		[ ...intro, ...wideBeat, ...sections[ 0 ], ...galleryBeat, ...sections[ 1 ], quote, ...sections[ 2 ], ...fullBeat, ...ending ],
		[ ...intro, quote, ...sections[ 0 ], ...galleryBeat, ...sections[ 1 ], ...fullBeat, ...sections[ 2 ], ...wideBeat, ...ending ],
		[ ...intro, ...sections[ 0 ], ...fullBeat, ...sections[ 1 ], quote, ...wideBeat, ...sections[ 2 ], ...galleryBeat, ...ending ],
	];

	return layouts[ index % layouts.length ].join( '\n\n' );
}

function buildVietnameseBody( title, definition, index, includeInline ) {
	const marker = includeInline ? '<!--pgds-preview-inline-image-->' : '';
	const detail = categoryDetails[ definition.slug ];
	return [
		`<p><strong>${ title }</strong> là bài viết mô phỏng dành cho môi trường phát triển PGDS. Nội dung được xây dựng như một hồ sơ biên tập hoàn chỉnh về ${ definition.focus }, với bối cảnh, nhân vật và số liệu đều mang tính minh họa. Mục tiêu là giúp người dùng kiểm tra bố cục bài dài, nhịp đọc, ảnh đại diện và các quan hệ nội dung trong CMS.</p>`,
		'<p>Câu chuyện bắt đầu từ những quan sát rất cụ thể: cách mọi người chuẩn bị không gian, phân chia công việc, tiếp nhận người mới và ghi lại điều cần cải thiện. Thay vì chỉ mô tả kết quả, bài viết chú ý đến tiến trình, những quyết định nhỏ và lý do khiến một hoạt động có thể duy trì lâu dài. Đây cũng là cách dữ liệu preview tạo cảm giác gần với một bài báo thật mà không gán phát ngôn cho tổ chức hay cá nhân có thật.</p>',
		'<h2>Bắt đầu từ nhu cầu của cộng đồng</h2>',
		`<p>Ở lát cắt thứ ${ index + 1 } của chuyên mục ${ definition.label }, nhóm biên tập giả định dành thời gian hỏi người tham gia về nhu cầu trước khi đưa ra giải pháp. Người cao tuổi quan tâm đến khả năng tiếp cận, người trẻ muốn được giao việc có trách nhiệm, còn gia đình cần thông tin rõ ràng về thời gian và cách tham gia. Khi các nhu cầu được đặt cạnh nhau, kế hoạch trở nên thực tế và dễ kiểm chứng hơn.</p>`,
		'<p>Một bảng công việc ngắn được sử dụng để theo dõi người phụ trách, thời hạn, nguồn lực và phản hồi sau hoạt động. Cách làm này không biến tinh thần phụng sự thành thủ tục; ngược lại, nó giúp lòng tốt đi đến đúng người và tránh phụ thuộc vào trí nhớ của một vài cá nhân. Những việc chưa hoàn thành cũng được ghi nhận thẳng thắn để lần tổ chức sau có điểm bắt đầu rõ ràng.</p>',
		`<h2>${ detail.heading }</h2>`,
		`<p>${ detail.paragraph }</p>`,
		marker,
		'<blockquote><p>Giá trị bền vững không nằm ở một khoảnh khắc nổi bật, mà ở khả năng cùng nhau chăm sóc những việc nhỏ sau khi chương trình đã kết thúc.</p></blockquote>',
		'<h2>Từ trải nghiệm đến thực hành hằng ngày</h2>',
		'<p>Phần tiếp theo nhìn vào cách kinh nghiệm được chuyển thành thói quen. Một cuộc họp ngắn sau chương trình, vài câu hỏi mở và bản tổng kết dễ đọc thường hữu ích hơn một báo cáo dài nhưng không có người sử dụng. Người tham gia được khuyến khích nói về điều đã giúp họ cảm thấy được chào đón, điều còn gây khó khăn và một thay đổi nhỏ có thể thử ngay trong tuần tới.</p>',
		'<ul><li>Ghi lại một quan sát cụ thể thay vì đánh giá chung chung.</li><li>Phân công người theo dõi việc tiếp nối sau sự kiện.</li><li>Ưu tiên giải pháp có thể duy trì bằng nguồn lực sẵn có.</li><li>Xin phản hồi từ cả người tổ chức lẫn người thụ hưởng.</li></ul>',
		'<p>Về mặt trình bày, bài viết kết hợp tiêu đề phụ, trích dẫn, danh sách và hình ảnh do Media Library quản lý. Ảnh có thông tin tác giả, giấy phép, mô tả và văn bản thay thế; quan hệ featured, inline hoặc gallery đều có thể chỉnh sửa trong WordPress. Điều này giúp cùng một fixture kiểm tra được trang danh sách, trang chi tiết, tìm kiếm, bài liên quan và trải nghiệm biên tập.</p>',
		'<p>Khi khép lại, câu chuyện không đưa ra một kết luận tuyệt đối. Nó mời người đọc chọn một việc vừa sức, quan sát tác động và quay lại điều chỉnh. Cách tiếp cận ấy phù hợp với tinh thần học hỏi liên tục: tôn trọng truyền thống, lắng nghe hoàn cảnh hiện tại và đo giá trị bằng những thay đổi có thể nhìn thấy trong đời sống thường ngày.</p>',
	].filter( Boolean ).join( '' );
}

function buildVideoBody( title, index ) {
	return [
		`<p><strong>${ title }</strong> là fixture video thứ ${ index + 1 } của môi trường preview. Clip là thành phần chính; phần chữ chỉ cung cấp bối cảnh cần thiết để người xem hiểu chủ đề, nhân vật và thời điểm ghi hình.</p>`,
		'<h2>Nội dung chính của video</h2>',
		'<p>Phóng sự đi theo diễn biến bằng hình ảnh, âm thanh hiện trường và các khoảnh khắc quan sát ngắn. Poster được quản lý trong Media Library, còn YouTube ID, tiêu đề đồng bộ và thời lượng được lưu ở các trường chuyên biệt để kiểm tra đầy đủ luồng biên tập Video.</p>',
		'<p>Người biên tập có thể thay sapo hoặc poster mà không phải viết lại một bài dài. Nội dung mô tả này cố ý gọn, giúp việc review tập trung vào trình phát, trạng thái video, khả năng hiển thị trên danh sách và trải nghiệm xem trên màn hình nhỏ.</p>',
	].join( '' );
}

function buildEnglishBody( title, definition, index, includeInline ) {
	const marker = includeInline ? '<!--pgds-preview-inline-image-->' : '';
	return [
		`<p><strong>${ title }</strong> is a synthetic editorial feature created for the PGDS development environment. It presents a complete, reviewable story about ${ definition.focus }. Places, participants and measurements are illustrative, allowing the fixture to test realistic publishing workflows without attributing statements to real people or institutions.</p>`,
		'<p>The report begins with observable details: how a space is prepared, how responsibilities are shared, how newcomers are welcomed and how the team records what should improve. Focusing on process makes the story useful beyond a single event. It also gives editors enough varied material to assess typography, cards, excerpts, image handling, search results and related-content behavior.</p>',
		'<h2>Starting with community needs</h2>',
		`<p>This is entry ${ index + 1 } in the Vietnam Buddhism preview collection. The fictional editorial team first asks participants what would make the activity accessible and meaningful. Older visitors mention clear directions and places to rest. Younger volunteers ask for genuine responsibility. Families want a schedule they can understand before arriving. Bringing these perspectives together produces a plan that can be reviewed rather than assumed.</p>`,
		'<p>A short working document tracks ownership, timing, available resources and follow-up. This does not turn compassionate service into bureaucracy. It protects the work from depending on one person’s memory and helps support reach the people it was designed for. Unfinished tasks remain visible so the next gathering begins with evidence instead of starting over.</p>',
		marker,
		'<blockquote><p>A living tradition becomes credible when care is expressed through small actions that continue after the public moment has ended.</p></blockquote>',
		'<h2>Turning experience into daily practice</h2>',
		'<p>The second part considers how lessons become habits. A brief reflection after an activity, several open questions and a readable summary often provide more value than a long report no one revisits. Participants describe what helped them feel included, what created friction and one modest change that could be tested during the following week.</p>',
		'<ul><li>Record one specific observation instead of a broad judgement.</li><li>Name the person responsible for the next step.</li><li>Choose an approach that existing resources can sustain.</li><li>Invite feedback from organizers and participants alike.</li></ul>',
		'<p>The article combines headings, a pull quotation, a list and imagery managed in the WordPress Media Library. Every image carries source, author, licence, description and alternative text. Featured, inline, gallery and video-poster relationships remain editable through native CMS controls, so the same fixture can exercise archive pages, details, search, related stories and editorial changes.</p>',
		'<h2>Reading heritage in its local context</h2>',
		'<p>Vietnam Buddhism stories distinguish direct observation from community memory and from facts that require documentary confirmation. Architectural materials, changing patterns of use, seasonal rituals and the work of volunteers are described together. This gives readers a sense of how heritage remains active while giving editors enough specificity to review the English-only publication flow.</p>',
		'<p>The visual selection follows the same principle. A wide view establishes place, a closer frame identifies an activity and an attributed caption preserves context. The English surface uses separate Media Library records with English alternative text and descriptions, even when a licensed source image is shared with the Vietnamese fixtures.</p>',
		'<p>The report closes without claiming a universal answer. Readers are invited to choose a manageable action, observe its effect and revise the approach. That rhythm reflects an important quality of contemporary Buddhist community work in Vietnam: respect for inherited practice, attention to present conditions and a willingness to learn through responsible participation.</p>',
	].filter( Boolean ).join( '' );
}

const articles = [];
const featured = [];
const inlineImages = [];
const galleries = [];
const videoPosters = [];
let globalIndex = 0;

for ( const [ categoryIndex, definition ] of categories.entries() ) {
	for ( let index = 0; index < 20; index++ ) {
		const sourceId = `preview-2026-${ definition.slug }-${ String( index + 1 ).padStart( 2, '0' ) }`;
		const emagazineStory = definition.slug === 'emagazine' ? emagazineStories[ index ] : null;
		const subject = emagazineStory?.title ?? definition.subjects[ index % definition.subjects.length ];
		const angle = definition.language === 'en' ? englishAngles[ index ] : vietnameseAngles[ index ];
		const title = emagazineStory?.title ?? `${ subject }: ${ angle }`;
		const includeInline = definition.slug !== 'video' && ( definition.slug === 'emagazine' || index % 5 === 0 );
		const cats = definition.parent ? [ definition.parent, definition.slug ] : [ definition.slug ];
		const surfacePhotoIds = definition.language === 'en' ? englishPhotoAssetIds : photoAssetIds;
		const photoId = surfacePhotoIds[ ( globalIndex + categoryIndex * 3 ) % surfacePhotoIds.length ];
		const inlinePhotoId = surfacePhotoIds[ ( globalIndex + 7 ) % surfacePhotoIds.length ];
		const publishedDate = new Date( Date.UTC( 2026, 8, 7, 2, 0, 0 ) - globalIndex * 6 * 60 * 60 * 1000 )
			.toISOString()
			.slice( 0, 19 )
			.replace( 'T', ' ' );
		const video = definition.slug === 'video' ? videoCatalog[ index % videoCatalog.length ] : null;

		articles.push( {
			source_id: sourceId,
			title,
			slug: `${ slugify( subject ) }-${ definition.slug }-${ String( index + 1 ).padStart( 2, '0' ) }-preview`,
			sapo: emagazineStory?.sapo ?? ( definition.language === 'en'
				? `A development-only report on ${ definition.focus }, prepared with structured text and CMS-managed media for editorial review.`
				: `Bài viết mô phỏng về ${ definition.focus }, có cấu trúc nội dung và media do CMS quản lý để phục vụ kiểm thử biên tập.` ),
			body_html: definition.slug === 'video'
				? buildVideoBody( title, index )
				: definition.slug === 'emagazine'
					? buildEmagazineBody( emagazineStory, index )
				: definition.language === 'en'
					? buildEnglishBody( title, definition, index, includeInline )
					: buildVietnameseBody( title, definition, index, includeInline ),
			primary_cat: definition.slug,
			cats,
			tags: definition.language === 'en'
				? [ definition.label, 'preview dataset', 'community practice' ]
				: [ definition.label, 'dữ liệu preview', 'đời sống Phật giáo' ],
			author: 'admin',
			published_at: publishedDate,
			featured_image_url: '',
			gallery: [],
			youtube_url: video?.id ?? '',
			source: definition.language === 'en'
				? 'PGDS preview dataset'
				: definition.slug === 'emagazine'
					? `Ảnh minh họa cho “${ emagazineStory.title }”: Bộ dữ liệu preview PGDS`
					: 'Bộ dữ liệu preview PGDS',
			old_url: '',
		} );

		featured.push( { source_id: sourceId, asset_id: video?.poster ?? photoId } );
		if ( definition.slug === 'emagazine' ) {
			inlineImages.push(
				{ source_id: sourceId, asset_id: inlinePhotoId, size: 'large', marker: '<!--pgds-preview-emag-wide-->', align: 'wide', class_name: 'pgds-emagazine-figure' },
				{ source_id: sourceId, asset_id: photoAssetIds[ ( globalIndex + 9 ) % photoAssetIds.length ], size: 'full', marker: '<!--pgds-preview-emag-full-->', align: 'full', class_name: 'pgds-emagazine-figure pgds-emagazine-figure--full' },
			);
		} else if ( includeInline ) {
			inlineImages.push( { source_id: sourceId, asset_id: inlinePhotoId, size: 'large' } );
		}
		if ( definition.slug === 'emagazine' ) {
			galleries.push( {
				source_id: sourceId,
				asset_ids: [
					photoAssetIds[ ( globalIndex + 11 ) % photoAssetIds.length ],
					photoAssetIds[ ( globalIndex + 15 ) % photoAssetIds.length ],
				],
				columns: 2,
				size: 'large',
			} );
		}
		if ( video ) {
			videoPosters.push( { source_id: sourceId, asset_id: video.poster } );
		}

		globalIndex++;
	}
}

mediaManifest.schema_version = 2;
mediaManifest.notice = 'Development-only preview media for the 180-article editorial-surface dataset. Never deploy these fixtures as newsroom media.';
mediaManifest.assignments = {
	featured,
	inline_images: inlineImages,
	galleries,
	video_posters: videoPosters,
};

fs.writeFileSync( contentPath, `${ JSON.stringify( articles, null, 2 ) }\n` );
fs.writeFileSync( mediaPath, `${ JSON.stringify( mediaManifest, null, 2 ) }\n` );

process.stdout.write(
	`Generated ${ articles.length } articles, ${ featured.length } featured images, ` +
	`${ inlineImages.length } inline images, ${ galleries.length } galleries and ` +
	`${ videoPosters.length } video posters.\n`
);
